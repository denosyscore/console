<?php

declare(strict_types=1);

namespace Denosys\Console;

use Closure;

/**
 * Adapts an #[AsCommand] class to CommandInterface. Its name, description
 * and definition are read without building it, so listing commands and
 * showing help never resolve its dependencies.
 *
 * @internal
 */
final class InvokableCommand implements CommandInterface
{
    private readonly AsCommand $attribute;

    /**
     * @param class-string $class
     * @param Closure(class-string): object $resolve
     */
    public function __construct(private readonly string $class, private readonly Closure $resolve)
    {
        $attributes = (new \ReflectionClass($class))->getAttributes(AsCommand::class);
        if ($attributes === [] || !method_exists($class, '__invoke')) {
            throw new \InvalidArgumentException("[{$class}] must have #[AsCommand] and an __invoke() method.");
        }
        $this->attribute = $attributes[0]->newInstance();
    }

    public static function supports(string $class): bool
    {
        return class_exists($class)
            && (new \ReflectionClass($class))->getAttributes(AsCommand::class) !== []
            && method_exists($class, '__invoke');
    }

    public function getName(): string
    {
        return $this->attribute->name;
    }

    public function getDescription(): string
    {
        return $this->attribute->description;
    }

    public function configure(): CommandDefinition
    {
        if (method_exists($this->class, 'definition')) {
            $definition = ($this->class)::definition();
            if ($definition instanceof CommandDefinition) {
                return $definition;
            }
        }

        return new CommandDefinition();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = ($this->resolve)($this->class);
        if (!is_callable($command)) {
            throw new \LogicException("Command [{$this->class}] is not invokable.");
        }

        $result = $command($input, $output);

        return is_int($result) ? $result : 0;
    }
}
