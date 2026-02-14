<?php

declare(strict_types=1);

namespace CFXP\Core\Console\Command;

use CFXP\Core\Console\CommandDefinition;
use CFXP\Core\Console\CommandInterface;
use CFXP\Core\Console\InputInterface;
use CFXP\Core\Console\OutputInterface;
use CFXP\Core\Container\ContainerInterface;
use CFXP\Core\Routing\RouteCache;

class RoutesClearCommand implements CommandInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function getName(): string
    {
        return 'routes:clear';
    }

    public function getDescription(): string
    {
        return 'Clear cached routes';
    }

    public function configure(): CommandDefinition
    {
        return new CommandDefinition();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheFile = $this->resolveRouteCacheFile();

        (new RouteCache())->clear($cacheFile);

        $output->success("Route cache cleared at {$cacheFile}");

        return 0;
    }

    private function resolveRouteCacheFile(): string
    {
        $app = $this->resolveApp();
        if ($app !== null && method_exists($app, 'routeCacheFile')) {
            /** @var string $cacheFile */
            $cacheFile = $app->routeCacheFile();
            return $cacheFile;
        }

        return $this->resolveBasePath() . '/storage/core/cache/routes.php';
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
}
