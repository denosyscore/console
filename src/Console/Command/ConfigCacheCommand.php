<?php

declare(strict_types=1);

namespace CFXP\Core\Console\Command;

use CFXP\Core\Config\Configuration;
use CFXP\Core\Console\CommandDefinition;
use CFXP\Core\Console\CommandInterface;
use CFXP\Core\Console\InputInterface;
use CFXP\Core\Console\OutputInterface;
use CFXP\Core\Container\ContainerInterface;

class ConfigCacheCommand implements CommandInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function getName(): string
    {
        return 'config:cache';
    }

    public function getDescription(): string
    {
        return 'Cache configuration files for faster bootstrap';
    }

    public function configure(): CommandDefinition
    {
        return new CommandDefinition();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $this->resolveConfigPath();
        $cacheFile = $this->resolveConfigCacheFile();

        $configuration = new Configuration($configPath, $cacheFile);
        $configuration->saveToCache();

        $output->success("Configuration cached at {$cacheFile}");

        return 0;
    }

    private function resolveConfigPath(): string
    {
        if ($this->container->has('path.config')) {
            /** @var string $path */
            $path = $this->container->get('path.config');
            return $path;
        }

        $basePath = $this->resolveBasePath();
        return $basePath . '/config';
    }

    private function resolveConfigCacheFile(): string
    {
        $app = $this->resolveApp();
        if ($app !== null && method_exists($app, 'configCacheFile')) {
            /** @var string $cacheFile */
            $cacheFile = $app->configCacheFile();
            return $cacheFile;
        }

        $basePath = $this->resolveBasePath();
        return $basePath . '/storage/core/cache/config.php';
    }

    private function resolveBasePath(): string
    {
        if ($this->container->has('path.base')) {
            /** @var string $path */
            $path = $this->container->get('path.base');
            return rtrim($path, '/');
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
}
