<?php

declare(strict_types=1);

namespace CFXP\Core\Console\Command;

use CFXP\Core\Bootstrap\Configuration\RoutesConfiguration;
use CFXP\Core\Console\CommandDefinition;
use CFXP\Core\Console\CommandInterface;
use CFXP\Core\Console\InputInterface;
use CFXP\Core\Console\OutputInterface;
use CFXP\Core\Container\ContainerInterface;
use CFXP\Core\Routing\RouteCache;
use CFXP\Core\Routing\RouteLoader;
use Denosys\Routing\Dispatcher;
use Denosys\Routing\MiddlewareRegistry;
use Denosys\Routing\RouteCollection;
use Denosys\Routing\RouteManager;
use Denosys\Routing\Router;
use Throwable;

class RoutesCacheCommand implements CommandInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function getName(): string
    {
        return 'routes:cache';
    }

    public function getDescription(): string
    {
        return 'Cache routes for faster route registration';
    }

    public function configure(): CommandDefinition
    {
        return new CommandDefinition();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheFile = $this->resolveRouteCacheFile();
        $router = $this->buildSourceRouter();

        $routeCache = new RouteCache();
        $routeCache->build($router->getRouteCollection(), $cacheFile);

        $output->success(sprintf(
            'Routes cached at %s (%d route(s))',
            $cacheFile,
            $router->getRouteCollection()->count()
        ));

        return 0;
    }

    private function buildSourceRouter(): Router
    {
        $registry = $this->createSourceMiddlewareRegistry();
        $routeCollection = new RouteCollection();
        $routeManager = new RouteManager();
        $dispatcher = Dispatcher::withDefaults(
            routeCollection: $routeCollection,
            routeManager: $routeManager,
            container: $this->container,
            middlewareRegistry: $registry
        );

        $router = new Router(
            container: $this->container,
            routeCollection: $routeCollection,
            routeManager: $routeManager,
            dispatcher: $dispatcher,
            middlewareRegistry: $registry
        );

        $basePath = $this->resolveBasePath();

        $configuration = $this->resolveRoutesConfiguration();
        if ($configuration !== null) {
            if ($configuration->getWebPath() !== null) {
                $webPath = $this->resolvePath($configuration->getWebPath(), $basePath);
                $this->loadRoutesWithMiddleware($router, $registry, 'web', $webPath);
            }

            if ($configuration->getApiPath() !== null) {
                $apiPath = $this->resolvePath($configuration->getApiPath(), $basePath);
                $this->loadRoutesWithMiddleware($router, $registry, 'api', $apiPath, '/api');
            }

            if ($configuration->getCustomConfigurator() !== null) {
                ($configuration->getCustomConfigurator())($router);
            }

            return $router;
        }

        $this->loadRoutesWithMiddleware($router, $registry, 'web', $basePath . '/routes/web.php');

        return $router;
    }

    private function resolveRoutesConfiguration(): ?RoutesConfiguration
    {
        try {
            /** @var RoutesConfiguration $configuration */
            $configuration = $this->container->get(RoutesConfiguration::class);
        } catch (Throwable) {
            return null;
        }

        if (!$configuration->hasConfiguration()) {
            return null;
        }

        return $configuration;
    }

    private function createSourceMiddlewareRegistry(): MiddlewareRegistry
    {
        $registry = new MiddlewareRegistry();

        if (!$this->container->has(MiddlewareRegistry::class)) {
            return $registry;
        }

        /** @var MiddlewareRegistry $registered */
        $registered = $this->container->get(MiddlewareRegistry::class);

        $registry->aliases($registered->getAliases());
        foreach ($registered->getGroups() as $group => $middleware) {
            $registry->group($group, $middleware);
        }

        return $registry;
    }

    private function loadRoutesWithMiddleware(
        Router $router,
        MiddlewareRegistry $registry,
        string $group,
        string $path,
        string $prefix = ''
    ): void {
        $middleware = $registry->resolve($group);

        if (!empty($middleware) || $prefix !== '') {
            $router->middleware($middleware)->group($prefix, fn($g) => RouteLoader::loadWebRoutes($g, $path));
            return;
        }

        RouteLoader::loadWebRoutes($router, $path);
    }

    private function resolvePath(string $path, string $basePath): string
    {
        return str_starts_with($path, '/') ? $path : $basePath . '/' . $path;
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
