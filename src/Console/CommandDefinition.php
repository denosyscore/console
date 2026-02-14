<?php

declare(strict_types=1);

namespace Denosys\Console;

/**
 * Value object defining command arguments and options.
 * 
 * Fluent builder for command definition.
 */
class CommandDefinition
{
    /** @var array<array{name: string, mode: int, description: string, default: mixed}> */
private array $arguments = [];
    
    /** @var array<array{name: string, shortcut: ?string, mode: int, description: string, default: mixed}> */
private array $options = [];
    
    private string $help = '';

    // Argument modes
    public const ARGUMENT_REQUIRED = 1;
    public const ARGUMENT_OPTIONAL = 2;
    public const ARGUMENT_IS_ARRAY = 4;

    // Option modes
    public const OPTION_NONE = 1;       // --option (flag)
    public const OPTION_REQUIRED = 2;   // --option=VALUE (required value)
    public const OPTION_OPTIONAL = 4;   // --option[=VALUE] (optional value)
    public const OPTION_IS_ARRAY = 8;   // --option=V1 --option=V2

    /**
     * Add an argument.
     */
    public function addArgument(
        string $name,
        int $mode = self::ARGUMENT_OPTIONAL,
        string $description = '',
        mixed $default = null,
    ): self {
        $this->arguments[] = [
            'name' => $name,
            'mode' => $mode,
            'description' => $description,
            'default' => $default,
        ];
        return $this;
    }

    /**
     * Add an option.
     */
    public function addOption(
        string $name,
        ?string $shortcut = null,
        int $mode = self::OPTION_NONE,
        string $description = '',
        mixed $default = null,
    ): self {
        $this->options[] = [
            'name' => $name,
            'shortcut' => $shortcut,
            'mode' => $mode,
            'description' => $description,
            'default' => $default,
        ];
        return $this;
    }

    /**
     * Set help text.
     */
    public function setHelp(string $help): self
    {
        $this->help = $help;
        return $this;
    }

    /**

     * @return array<string, mixed>

     */

public function getArguments(): array

    {
        return $this->arguments;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getHelp(): string
    {
        return $this->help;
    }
}
