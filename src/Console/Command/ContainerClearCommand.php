<?php

declare(strict_types=1);

namespace CFXP\Core\Console\Command;

use CFXP\Core\Console\CommandDefinition;
use CFXP\Core\Console\CommandInterface;
use CFXP\Core\Console\InputInterface;
use CFXP\Core\Console\OutputInterface;
use CFXP\Core\Container\ContainerInterface;

class ContainerClearCommand implements CommandInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function getName(): string
    {
        return 'container:clear';
    }

    public function getDescription(): string
    {
        return 'Clear compiled container cache';
    }

    public function configure(): CommandDefinition
    {
        return new CommandDefinition();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheFile = $this->resolveContainerCacheFile();
        $cacheDirectory = dirname($cacheFile);
        $preloadFile = $cacheDirectory . '/preload.php';
        $metricsFile = $cacheDirectory . '/container-metrics.json';

        $this->removeCacheFile($cacheFile);
        $this->removeCacheFile($preloadFile);
        $this->removeCacheFile($metricsFile);

        $output->success("Container cache cleared at {$cacheFile}");

        return 0;
    }

    private function resolveContainerCacheFile(): string
    {
        $app = $this->resolveApp();
        if ($app !== null && method_exists($app, 'containerCacheFile')) {
            /** @var string $cacheFile */
            $cacheFile = $app->containerCacheFile();
            return $cacheFile;
        }

        return $this->resolveBasePath() . '/storage/core/cache/container.php';
    }

    private function resolveBasePath(): string
    {
        if ($this->container->has('path.base')) {
            /** @var string $basePath */
            $basePath = $this->container->get('path.base');
            return rtrim($basePath, '/');
        }

        return getcwd() ?: '.';
    }

    private function resolveApp(): ?object
    {
        if (!$this->container->has('app')) {
            return null;
        }

        $app = $this->container->get('app');
        return is_object($app) ? $app : null;
    }

    private function removeCacheFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }

        @unlink($path);
    }
}
