<?php

namespace Castor;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function \log as log;

/** @internal */
class HttpHelper
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function http_client(): HttpClientInterface
    {
        return $this->httpClient;
    }

    /**
     * @param string|null $filePath Path to save the downloaded content. If null, the filename is determined from the URL or content disposition.
     */
    public function http_download(string $url, ?string $filePath = null, string $method = 'GET', array $options = [], bool $stream = true): ResponseInterface
    {
        $this->logger->info('Download starts for URL: "{url}"', ['url' => $url, 'method' => $method, 'stream' => $stream]);

        $response = $this->httpClient->request($method, $url, $options);
        $totalSize = $response->getHeaders()['content-length'][0] ?? 0;

        if (null === $filePath) {
            $disposition = $response->getHeaders(false)['content-disposition'][0] ?? null;
            if (null !== $disposition && preg_match('/filename="([^"]+)"/', $disposition, $matches)) {
                $filename = $matches[1];
            } else {
                // Fallback to parsing the URL for a file name
                $path = parse_url($url, \PHP_URL_PATH);
                $filename = basename($path);
            }
            $filePath = PathHelper::getRoot() . \DIRECTORY_SEPARATOR . $filename;
            $this->logger->info('Determined the filename: "{filename}" for download.', ['filename' => $filename]);
        }

        if ($stream) {
            $fileStream = fopen($filePath, 'w');
            if (false === $fileStream) {
                throw new \RuntimeException(sprintf('Cannot open file "%s" for writing.', $filePath));
            }

            $downloadedSize = 0;
            $lastLogTime = time();
            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($fileStream, $chunk->getContent());
                $downloadedSize += \strlen($chunk->getContent());
                if (time() - $lastLogTime >= 2) {
                    $this->logger->info(
                        sprintf('Download progress: %s/%s', $this->formatSize($downloadedSize), $this->formatSize($totalSize)),
                        ['filePath' => $filePath]
                    );
                    $lastLogTime = time();
                }
            }

            // To display the 100%
            $this->logger->info(
                sprintf('Download progress: %s/%s', $this->formatSize($downloadedSize), $this->formatSize($totalSize)),
                ['filePath' => $filePath]
            );

            fclose($fileStream);
            $this->logger->info('Finished downloading "{filePath}".', ['url' => $url, 'filePath' => $filePath, 'size' => $totalSize]);

            return $response;
        }

        $content = $response->getContent();
        file_put_contents($filePath, $content);
        $this->logger->info('Finished downloading "{filePath}".', ['url' => $url, 'filePath' => $filePath, 'size' => \strlen($content)]);

        return $response;
    }

    public function http_upload(string $url, string $filePath, string $method = 'POST', array $options = []): ResponseInterface
    {
        if (isset($options['body'])) {
            throw new \InvalidArgumentException('Request body shouldn\'t be set as it will be filled with the file content');
        }

        if (!$this->filesystem->exists($filePath)) {
            throw new \RuntimeException(sprintf('File "%s" does not exist.', $filePath));
        }

        // Determine file name from filePath if not provided in options
        if (!isset($options['headers']['Content-Disposition'])) {
            $filename = basename($filePath);
            $options['headers']['Content-Disposition'] = 'attachment; filename="' . $filename . '"';
        }

        $fileStream = fopen($filePath, 'r');
        if (false === $fileStream) {
            throw new \RuntimeException(sprintf('Cannot open file "%s".', $filePath));
        }

        $options['body'] = $fileStream;

        $response = $this->httpClient->request($method, $url, $options);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException(sprintf('Failed to upload file. Server responded with status code %s.', $response->getStatusCode()));
        }

        return $response;
    }

    public function http_request(string $method, string $url, array $options): ResponseInterface
    {
        return $this->httpClient->request($method, $url, $options);
    }

    private function formatSize(int $bytes, int $precision = 2): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $log = log($bytes, 1024);
        $pow = floor($log);
        $size = round($bytes / (1024 ** $pow), $precision);

        return $size . ' ' . $units[$pow - 1];
    }
}
