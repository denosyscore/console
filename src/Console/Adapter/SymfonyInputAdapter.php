<?php

declare(strict_types=1);

namespace CFXP\Core\Console\Adapter;

use CFXP\Core\Console\InputInterface;
use Symfony\Component\Console\Input\InputInterface as SymfonyInput;

/**
 * Adapter wrapping Symfony's InputInterface.
 * 
 * This enables our commands to use our InputInterface abstraction
 * while Symfony Console powers the implementation.
 */
class SymfonyInputAdapter implements InputInterface
{
    public function __construct(
        private readonly SymfonyInput $symfonyInput,
    ) {}

    public function getArgument(string $name): mixed
    {
        return $this->symfonyInput->getArgument($name);
    }

    /**

     * @return array<string, mixed>

     */

public function getArguments(): array

    {
        return $this->symfonyInput->getArguments();
    }

    public function hasArgument(string $name): bool
    {
        return $this->symfonyInput->hasArgument($name);
    }

    public function getOption(string $name): mixed
    {
        return $this->symfonyInput->getOption($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->symfonyInput->getOptions();
    }

    public function hasOption(string $name): bool
    {
        return $this->symfonyInput->hasOption($name);
    }

    public function isInteractive(): bool
    {
        return $this->symfonyInput->isInteractive();
    }

    /**
     * Get the underlying Symfony input.
     */
    public function getSymfonyInput(): SymfonyInput
    {
        return $this->symfonyInput;
    }
}
