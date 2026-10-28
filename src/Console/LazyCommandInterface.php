<?php

declare(strict_types=1);

namespace Denosys\Console;

/**
 * Optional metadata for commands whose dependencies should resolve on execution.
 * Existing CommandInterface implementations remain supported unchanged.
 */
interface LazyCommandInterface extends CommandInterface
{
    public static function commandName(): string;

    public static function commandDescription(): string;

    public static function commandDefinition(): CommandDefinition;
}
