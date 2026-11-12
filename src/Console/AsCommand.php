<?php

declare(strict_types=1);

namespace Denosys\Console;

use Attribute;

/**
 * Declares a console command on a plain invokable class:
 *
 *     #[AsCommand('cache:clear', 'Clear the configured cache')]
 *     final readonly class ClearCache
 *     {
 *         public function __construct(private Cache $cache) {}
 *         public function __invoke(InputInterface $input, OutputInterface $output): int { ... }
 *     }
 *
 * Arguments and options come from an optional static definition() method
 * returning a CommandDefinition. The class is built only when it runs.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsCommand
{
    public function __construct(public string $name, public string $description = '')
    {
    }
}
