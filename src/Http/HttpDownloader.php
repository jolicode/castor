<?php

namespace Castor\Http;

use Castor\Helper\PathHelper;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/** @internal */
class HttpDownloader
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @param string|null          $filePath Path to save the downloaded content. If null, the filename is determined from the URL or content disposition.
     * @param bool                 $stream   Controls whether the download is chunked (`true`), which is useful for large files as it uses less memory, or in one go (`false`)
     * @param array<string, mixed> $options  default values at {@see HttpClientInterface::OPTIONS_DEFAULTS}
     */
    public function download(string $url, ?string $filePath = null, string $method = 'GET', array $options = [], bool $stream = true): ResponseInterface
    {
        $this->logger->info('Starting http download', ['url' => $url]);

        $lastLogTime = time();
        $startTime = microtime(true);
        $finalLogDone = false;
        $userProvidedOnProgress = $options['on_progress'] ?? function (int $downloadedSize, int $totalSize) {};
        $totalDownloadedSize = 0;

        $options['on_progress'] = function (int $downloadedSize, int $totalSize) use ($userProvidedOnProgress, &$totalDownloadedSize, &$lastLogTime, &$finalLogDone, $url, $startTime) {
            $totalDownloadedSize = $downloadedSize;
            $percentage = $this->calculatePercentage($downloadedSize, $totalSize);
            $speed = $this->calculateSpeed($downloadedSize, $startTime);
            $formattedRemainingTime = $this->calculateRemainingTime($downloadedSize, $totalSize, (int) $speed);
            $logMessage = $totalSize > 0
                ? \sprintf(
                    'Download progress: %s/%s (%.2f%%) at %s/s, ETA: %s',
                    $this->formatSize($downloadedSize),
                    $this->formatSize($totalSize),
                    $percentage,
                    $this->formatSize((int) $speed),
                    $formattedRemainingTime
                )
                : \sprintf(
                    'Download progress: %s at %s/s',
                    $this->formatSize($downloadedSize),
                    $this->formatSize((int) $speed)
                );

            if (
                // Logs progress if 2 secs elapsed and below 100%
                (time() - $lastLogTime >= 2 && $percentage < 100)
                // Logs 100% only once; avoids multiple logs if data continues after reaching 100%
                || ($percentage >= 100 && !$finalLogDone)
            ) {
                $this->logger->info($logMessage, ['url' => $url]);
                $lastLogTime = time();

                if ($percentage >= 100) {
                    $finalLogDone = true;
                }
            }

            $userProvidedOnProgress($downloadedSize, $totalSize);
        };

        $response = $this->httpClient->request($method, $url, $options);

        if (null === $filePath) {
            $filename = $this->extractFileName($response, $url);
            $filePath = PathHelper::getRoot() . \DIRECTORY_SEPARATOR . $filename;
            $this->logger->info('Filename determined for http download', ['filename' => $filename, 'url' => $url]);
        }

        $this->filesystem->mkdir(\dirname($filePath));

        if (!$stream) {
            $content = $response->getContent();
            file_put_contents($filePath, $content);
            $this->logger->info('Download finished', ['url' => $url, 'filePath' => $filePath, 'size' => $this->formatSize($totalDownloadedSize)]);

            return $response;
        }

        $fileStream = fopen($filePath, 'w');
        if (false === $fileStream) {
            throw new \RuntimeException(\sprintf('Cannot open file "%s" for writing.', $filePath));
        }

        foreach ($this->httpClient->stream($response) as $chunk) {
            fwrite($fileStream, $chunk->getContent());
        }

        fclose($fileStream);

        $this->logger->info('Download finished', ['url' => $url, 'filePath' => $filePath, 'size' => $this->formatSize($totalDownloadedSize)]);

        return $response;
    }

    private function calculatePercentage(int $downloadedSize, int $totalSize): float
    {
        return $totalSize > 0 ? round(($downloadedSize / $totalSize) * 100, 2) : 0;
    }

    private function calculateSpeed(int $downloadedSize, float $startTime): float
    {
        $elapsedTime = microtime(true) - $startTime;

        return $elapsedTime > 0 ? $downloadedSize / $elapsedTime : 0;
    }

    private function calculateRemainingTime(int $downloadedSize, int $totalSize, float $speed): string
    {
        $remainingTime = $speed > 0 ? ($totalSize - $downloadedSize) / $speed : 0;

        return $this->formatTime((int) $remainingTime);
    }

    private function formatTime(float $seconds): string
    {
        if ($seconds < 60) {
            return \sprintf('%ds', $seconds);
        }

        $minutes = floor($seconds / 60);
        $seconds %= 60;
        if ($minutes < 60) {
            return \sprintf('%dm %ds', $minutes, $seconds);
        }

        $hours = floor($minutes / 60);
        $minutes %= 60;

        return \sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $log = log($bytes, 1024);
        $pow = floor($log);
        $size = $bytes / (1024 ** $pow);

        return \sprintf('%.2f %s', $size, $units[$pow - 1]);
    }

    private function extractFileName(ResponseInterface $response, string $url): string
    {
        $disposition = $response->getHeaders(false)['content-disposition'][0] ?? null;
        if (null !== $disposition && preg_match('/filename="([^"]+)"/', $disposition, $matches)) {
            $filename = $matches[1];
        } else {
            $parsedUrl = parse_url($url, \PHP_URL_PATH);
            if (!\is_string($parsedUrl)) {
                throw new \RuntimeException(\sprintf('Could not extract file name from URL: %s', $url));
            }
            $filename = basename($parsedUrl);
        }

        return $filename;
    }
}
