<?php

declare(strict_types=1);

namespace CFXP\Core\Console;

/**
 * Contract for console commands using composition.
 * 
 * Commands implement this interface rather than extending Symfony's Command.
 * The CommandAdapter bridges this interface to Symfony Console.
 */
interface CommandInterface
{
    /**
     * Get the command name (e.g., "migrate", "migrate:rollback").
     */
    public function getName(): string;

    /**
     * Get the command description.
     */
    public function getDescription(): string;

    /**
     * Execute the command.
     * 
     * @param InputInterface $input Abstraction over command input
     * @param OutputInterface $output Abstraction over command output
     * @return int Exit code (0 = success)
     */
    public function execute(InputInterface $input, OutputInterface $output): int;

    /**
     * Configure command arguments and options.
     * 
     * @return CommandDefinition
     */
    public function configure(): CommandDefinition;
}
