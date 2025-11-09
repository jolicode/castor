<?php

namespace Castor\Helper;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 *
 * Class is not readonly to enable Symfony DI lazy loading on PHP < 8.3
 */
#[Autoconfigure(lazy: true)]
class Installation
{
    private readonly Architecture $architecture;
    private readonly InstallationMethod $method;
    private readonly string $path;

    public function __construct(
        private readonly CacheItemPoolInterface&CacheInterface $cache,
    ) {
        $pharPath = \Phar::running(false);

        if (get_cfg_var('castor.static')) {
            $this->method = InstallationMethod::Static;
            $this->path = $pharPath;
        } elseif ($pharPath) {
            $this->method = InstallationMethod::Phar;
            $this->path = $pharPath;
        } else {
            $globalComposerPath = $this->cache->get('castor-composer-global-path', function (): string {
                $process = new Process(['composer', 'global', 'config', 'home', '--quiet']);
                $process->run();

                if (0 !== $process->getExitCode()) {
                    return '';
                }

                $path = trim($process->getOutput());

                if (!is_dir($path)) {
                    return '';
                }

                return $path;
            });

            if ($globalComposerPath && str_contains(__FILE__, $globalComposerPath)) {
                $this->method = InstallationMethod::ComposerGlobal;
                $this->path = $globalComposerPath . '/vendor/bin/castor';
            } elseif (str_contains(__FILE__, 'vendor' . \DIRECTORY_SEPARATOR . 'jolicode' . \DIRECTORY_SEPARATOR . 'castor')) {
                $this->method = InstallationMethod::Composer;
                $this->path = \dirname(__DIR__, 3) . '/vendor/bin/castor';
            } else {
                $this->method = InstallationMethod::Source;
                $this->path = \dirname(__DIR__, 2) . '/bin/castor';
            }
        }

        $this->architecture = match (php_uname('m')) {
            'arm64' => Architecture::Arm64,
            default => Architecture::Amd64,
        };
    }

    public function getArchitecture(): Architecture
    {
        return $this->architecture;
    }

    public function getMethod(): InstallationMethod
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
