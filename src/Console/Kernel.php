<?php

declare(strict_types=1);

namespace CFXP\Core\Console;

use CFXP\Core\Container\ContainerInterface;

/**
 * Base console kernel with auto-discovery.
 * 
 * Commands in app/Console/Commands are automatically discovered.
 * No manual registration needed!
 */
abstract class Kernel
{
    /**
     * Additional commands to register (besides auto-discovered ones).
     * 
     * @var array<class-string<CommandInterface>>
     */
    /** @var array<string, mixed> */

    protected array $commands = [];

    /**
     * Paths to scan for commands (relative to basePath).
     * 
     * @var array<string>
     */
    protected array $commandPaths = [
        'app/Console/Commands',
    ];

    /**
     * Namespace prefix for discovered commands.
     */
    protected string $commandNamespace = 'App\\Console\\Commands';

    /**
     * Namespace mapping by command path.
     *
     * @var array<string, string>
     */
    protected array $commandPathNamespaces = [
        'app/Console/Commands' => 'App\\Console\\Commands',
    ];

    /**
     * Framework core commands (always registered).
     * 
     * @var array<class-string<CommandInterface>>
     */
    protected array $coreCommands = [
        \CFXP\Core\Console\Command\MigrateCommand::class,
        \CFXP\Core\Console\Command\MigrateRollbackCommand::class,
        \CFXP\Core\Console\Command\MigrateStatusCommand::class,
        \CFXP\Core\Console\Command\MigrateFreshCommand::class,
        \CFXP\Core\Console\Command\MakeMigrationCommand::class,
        \CFXP\Core\Console\Command\MakeControllerCommand::class,
        \CFXP\Core\Console\Command\MakeModelCommand::class,
        \CFXP\Core\Console\Command\MakeProviderCommand::class,
        \CFXP\Core\Console\Command\MakeCommandCommand::class,
        \CFXP\Core\Console\Command\MakeMiddlewareCommand::class,
        \CFXP\Core\Console\Command\MakeRequestCommand::class,
        \CFXP\Core\Console\Command\MakeFactoryCommand::class,
        \CFXP\Core\Console\Command\MakeSeederCommand::class,
        \CFXP\Core\Console\Command\MakeMailCommand::class,
        \CFXP\Core\Console\Command\DbSeedCommand::class,
        \CFXP\Core\Console\Command\StubsPublishCommand::class,
        \CFXP\Core\Console\Command\QueueWorkCommand::class,
        \CFXP\Core\Console\Command\ConfigCacheCommand::class,
        \CFXP\Core\Console\Command\ConfigClearCommand::class,
        \CFXP\Core\Console\Command\RoutesCacheCommand::class,
        \CFXP\Core\Console\Command\RoutesClearCommand::class,
        \CFXP\Core\Console\Command\ContainerWarmupCommand::class,
        \CFXP\Core\Console\Command\ContainerClearCommand::class,
        \CFXP\Core\Console\Command\OptimizeCommand::class,
        \CFXP\Core\Console\Command\OptimizeClearCommand::class,
        \CFXP\Core\Console\Command\StartupBenchmarkCommand::class,
        \CFXP\Core\Console\Command\ViewClearCommand::class,
    ];

    public function __construct(
        protected readonly ContainerInterface $container,
        protected readonly string $basePath,
    ) {
        $this->configureCommandDiscovery();
    }

    /**
     * Bootstrap the console and return the application.
     */
    public function bootstrap(): Application
    {
        $console = new Application($this->container, 'CFXP Console', '1.0.0');

        // Register core framework commands
        foreach ($this->coreCommands as $commandClass) {
            $console->add($this->resolveCommand($commandClass));
        }

        // Auto-discover user commands
        foreach ($this->discoverCommands() as $commandClass) {
            $console->add($this->resolveCommand($commandClass));
        }

        // Register explicitly listed commands
        foreach ($this->commands as $commandClass) {
            $console->add($this->resolveCommand($commandClass));
        }

        return $console;
    }

    /**
     * Discover command classes from configured paths.
     * 
     * @return array<class-string<CommandInterface>>
     */
    protected function discoverCommands(): array
    {
        $commands = [];

        foreach ($this->commandPaths as $path) {
            $fullPath = $this->basePath . '/' . $path;
            
            if (!is_dir($fullPath)) {
                continue;
            }

            $files = glob($fullPath . '/*.php');
            
            foreach ($files as $file) {
                $className = $this->getClassFromFile($file, $path);
                
                if ($className && $this->isValidCommand($className)) {
                    $commands[] = $className;
                }
            }
        }

        return $commands;
    }

    /**
     * Get the fully qualified class name from a file.
     */
    protected function getClassFromFile(string $file, string $relativePath): ?string
    {
        $filename = pathinfo($file, PATHINFO_FILENAME);

        $namespace = $this->resolveCommandNamespace($relativePath);
        if ($namespace === null) {
            return null;
        }

        return $namespace . '\\' . $filename;
    }

    private function configureCommandDiscovery(): void
    {
        if (!$this->container->has('config')) {
            return;
        }

        try {
            $config = $this->container->get('config');

            $paths = $config->get('console.command_paths', $this->commandPaths);
            if (is_array($paths)) {
                $normalized = array_values(array_filter($paths, static fn(mixed $path): bool => is_string($path) && $path !== ''));

                if ($normalized !== []) {
                    $this->commandPaths = $normalized;
                }
            }

            $namespace = $config->get('console.command_namespace', $this->commandNamespace);
            if (is_string($namespace) && $namespace !== '') {
                $this->commandNamespace = trim($namespace, '\\');
            }

            $mapping = $config->get('console.command_namespaces', []);
            if (is_array($mapping)) {
                foreach ($mapping as $path => $mappedNamespace) {
                    if (is_string($path) && is_string($mappedNamespace) && $path !== '' && $mappedNamespace !== '') {
                        $this->commandPathNamespaces[$path] = trim($mappedNamespace, '\\');
                    }
                }
            }

            foreach ($this->commandPaths as $index => $path) {
                if (!isset($this->commandPathNamespaces[$path])) {
                    $this->commandPathNamespaces[$path] = $index === 0
                        ? $this->commandNamespace
                        : $this->convertPathToNamespace($path);
                }
            }
        } catch (\Throwable) {
            // Keep sane defaults when config is unavailable in constrained contexts.
        }
    }

    private function resolveCommandNamespace(string $relativePath): ?string
    {
        if (isset($this->commandPathNamespaces[$relativePath])) {
            return $this->commandPathNamespaces[$relativePath];
        }

        $namespace = $this->convertPathToNamespace($relativePath);

        return $namespace !== '' ? $namespace : null;
    }

    private function convertPathToNamespace(string $path): string
    {
        $namespace = str_replace('/', '\\', trim($path, '/'));

        return (string) preg_replace('/^app/', 'App', $namespace);
    }

    /**
     * Check if a class is a valid command.
     */
    protected function isValidCommand(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflection = new \ReflectionClass($className);
        
        return $reflection->implementsInterface(CommandInterface::class) 
            && !$reflection->isAbstract();
    }

    /**
     * Resolve a command instance.
     */
    protected function resolveCommand(string $commandClass): CommandInterface
    {
        return $this->instantiateCommand($commandClass);
    }

    /**
     * Instantiate a command with its dependencies.
     */
    protected function instantiateCommand(string $commandClass): CommandInterface
    {
        $reflection = new \ReflectionClass($commandClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $commandClass();
        }

        $parameters = $constructor->getParameters();
        $args = [];

        foreach ($parameters as $param) {
            $type = $param->getType();
            
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                
                if ($this->container->has($typeName)) {
                    $args[] = $this->container->get($typeName);
                    continue;
                }
            }

            // Handle common string parameters
            $paramName = $param->getName();
            if ($paramName === 'basePath' || $paramName === 'seedersPath') {
                $args[] = $this->basePath;
                continue;
            }

            // Check for default value
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            throw new \RuntimeException(
                "Cannot resolve parameter [{$paramName}] for command [{$commandClass}]"
            );
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * Run the console application.
     */
    public function handle(): int
    {
        return $this->bootstrap()->run();
    }
}
