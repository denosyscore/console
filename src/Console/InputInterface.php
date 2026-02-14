<?php

declare(strict_types=1);

namespace Denosys\Console;

/**
 * Abstraction over command input (arguments and options).
 * 
 * Wraps Symfony's InputInterface to maintain composition pattern.
 */
interface InputInterface
{
    /**
     * Get an argument by name.
     */
    public function getArgument(string $name): mixed;

    /**
     * Get all arguments.
      * @return array<string, mixed>
     */
    public function getArguments(): array;

    /**
     * Check if an argument exists.
     */
    public function hasArgument(string $name): bool;

    /**
     * Get an option by name.
     */
    public function getOption(string $name): mixed;

    /**
     * Get all options.
      * @return array<string, mixed>
     */
    public function getOptions(): array;

    /**
     * Check if an option exists.
     */
    public function hasOption(string $name): bool;

    /**
     * Check if the input is interactive.
     */
    public function isInteractive(): bool;
}
