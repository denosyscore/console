<?php

declare(strict_types=1);

namespace Denosys\Console;

use Closure;

/** @internal */
final class LazyCommandProxy implements CommandInterface
{
    /**
     * @param class-string<LazyCommandInterface> $commandClass
     * @param Closure(): CommandInterface $resolve
     */
    public function __construct(private string $commandClass, private Closure $resolve)
    {
    }

    public function getName(): string
    {
        return $this->commandClass::commandName();
    }

    public function getDescription(): string
    {
        return $this->commandClass::commandDescription();
    }

    public function configure(): CommandDefinition
    {
        return $this->commandClass::commandDefinition();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        return ($this->resolve)()->execute($input, $output);
    }
}
