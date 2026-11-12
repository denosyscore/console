<?php

declare(strict_types=1);

namespace Denosys\Console;

use Denosys\Container\ContainerInterface;

/**
 * Builds the console application from three sources, in order: this
 * package's own commands, the commands the host registers (the framework and
 * other packages), and the commands discovered in the application's command
 * directory. Configure it with constructor arguments; it is not subclassed.
 *
 * A command is a CommandInterface implementation or a plain invokable class
 * with #[AsCommand]. Commands with static metadata (#[AsCommand] or
 * LazyCommandInterface) are built only when they run.
 */
final class Kernel
{
    /** Commands this package owns: generators, stubs and startup caches. */
    private const CORE_COMMANDS = [
        Command\MakeMigrationCommand::class,
        Command\MakeControllerCommand::class,
        Command\MakeModelCommand::class,
        Command\MakeProviderCommand::class,
        Command\MakeCommandCommand::class,
        Command\MakeMiddlewareCommand::class,
        Command\MakeRequestCommand::class,
        Command\MakeFactoryCommand::class,
        Command\MakeSeederCommand::class,
        Command\MakeMailCommand::class,
        Command\StubsPublishCommand::class,
        Command\OptimizeCommand::class,
        Command\OptimizeClearCommand::class,
        Command\StartupBenchmarkCommand::class,
    ];

    /**
     * @param list<class-string> $commands Commands the host registers.
     * @param string|null $discoverIn Directory under $basePath scanned for commands; null disables discovery.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly string $basePath,
        private readonly array $commands = [],
        private readonly ?string $discoverIn = 'app/Console/Commands',
        private readonly string $discoverNamespace = 'App\\Console\\Commands',
        private readonly bool $withCoreCommands = true,
    ) {
    }

    public function bootstrap(): Application
    {
        $console = new Application($this->container, 'Denosys Console', '1.0.0');

        $classes = [
            ...($this->withCoreCommands ? self::CORE_COMMANDS : []),
            ...$this->commands,
            ...$this->discoverCommands(),
        ];
        foreach (array_values(array_unique($classes)) as $class) {
            $console->add($this->command($class));
        }

        return $console;
    }

    public function handle(): int
    {
        return $this->bootstrap()->run();
    }

    /**
     * @return list<class-string>
     */
    private function discoverCommands(): array
    {
        if ($this->discoverIn === null) {
            return [];
        }

        $directory = $this->basePath . '/' . trim($this->discoverIn, '/');
        $commands = [];
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $class = rtrim($this->discoverNamespace, '\\') . '\\' . pathinfo($file, PATHINFO_FILENAME);
            if (class_exists($class) && $this->isCommand($class)) {
                $commands[] = $class;
            }
        }

        return $commands;
    }

    /**
     * @param class-string $class
     */
    private function isCommand(string $class): bool
    {
        $reflection = new \ReflectionClass($class);
        if ($reflection->isAbstract()) {
            return false;
        }

        return $reflection->implementsInterface(CommandInterface::class) || InvokableCommand::supports($class);
    }

    /**
     * @param class-string $class
     */
    private function command(string $class): CommandInterface
    {
        if (InvokableCommand::supports($class)) {
            return new InvokableCommand($class, fn (string $command): object => $this->instantiate($command));
        }

        if (is_subclass_of($class, LazyCommandInterface::class)) {
            return new LazyCommandProxy($class, fn (): CommandInterface => $this->asCommand($this->instantiate($class)));
        }

        if (!is_subclass_of($class, CommandInterface::class)) {
            throw new \InvalidArgumentException("[{$class}] is not a console command: implement CommandInterface or add #[AsCommand] and __invoke().");
        }

        return $this->asCommand($this->instantiate($class));
    }

    private function asCommand(object $command): CommandInterface
    {
        if (!$command instanceof CommandInterface) {
            throw new \LogicException($command::class . ' does not implement ' . CommandInterface::class . '.');
        }

        return $command;
    }

    /**
     * Build a command, giving $basePath and $seedersPath string parameters the
     * application base path and resolving everything else from the container.
     *
     * @param class-string $class
     */
    private function instantiate(string $class): object
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin() && $this->container->has($type->getName())) {
                $arguments[] = $this->container->get($type->getName());
                continue;
            }
            if (in_array($parameter->getName(), ['basePath', 'seedersPath'], true)) {
                $arguments[] = $this->basePath;
                continue;
            }
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new \RuntimeException("Cannot resolve parameter [{$parameter->getName()}] for command [{$class}]");
        }

        return new $class(...$arguments);
    }
}
