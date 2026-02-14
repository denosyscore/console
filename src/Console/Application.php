<?php

declare(strict_types=1);

namespace Denosys\Console;

use Denosys\Console\Adapter\SymfonyCommandAdapter;
use Denosys\Container\ContainerInterface;
use Symfony\Component\Console\Application as SymfonyApplication;

/**
 * Console application powered by Symfony Console.
 * 
 * Uses composition to wrap Symfony Console while exposing
 * our own CommandInterface for command definitions.
 * 
 * User commands implement CommandInterface (no inheritance).
 * Symfony Console handles execution under the hood.
 */
class Application
{
    /**
     * @param array<string, mixed> $commands
     */
    private SymfonyApplication $symfonyApp;

    /** @var CommandInterface[] */
    /** @var array<string, mixed> */

    private array $commands = [];

    public function __construct(
        private readonly ContainerInterface $container,
        string $name = 'Denosys Console',
        string $version = '1.0.0',
    ) {
        $this->symfonyApp = new SymfonyApplication($name, $version);
        $this->symfonyApp->setAutoExit(false);
    }

    /**
     * Register a command.
     */
    public function add(CommandInterface $command): self
    {
        $this->commands[$command->getName()] = $command;
        
        // Wrap our command with Symfony adapter
        $adapter = new SymfonyCommandAdapter($command);
        if (method_exists($this->symfonyApp, 'addCommand')) {
            $this->symfonyApp->addCommand($adapter);
        } else {
            $this->symfonyApp->add($adapter);
        }
        
        return $this;
    }

    /**
     * Register a command by class name (resolved from container).
     * 
     * @param class-string<CommandInterface> $commandClass
     */
    public function addFromContainer(string $commandClass): self
    {
        $command = $this->container->get($commandClass);
        return $this->add($command);
    }

    /**
     * Run the console application.
     * 
     * @return int Exit code
     */
    public function run(): int
    {
        return $this->symfonyApp->run();
    }

    /**
     * Get all registered commands.
     * 
     * @return CommandInterface[]
     */
    /**
     * @return array<string, mixed>
     */
public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Get the underlying Symfony Application.
     */
    public function getSymfonyApplication(): SymfonyApplication
    {
        return $this->symfonyApp;
    }
}
