# denosyscore/console

Console kernel and command infrastructure

## Status

Initial extraction snapshot from denosyscore monorepo as of 2026-02-14.

## Installation

composer require denosyscore/console

## Included Modules

- src/Console/*
- src/stubs/*

## Lazy command registration

Commands that depend on resources unavailable during console startup can
implement the optional `LazyCommandInterface`. Its static metadata supplies
the command name, description, and definition for `list` and `help`; the
kernel resolves the command's constructor only when that command executes.
Existing `CommandInterface` implementations continue to register as before.

```php
final class InspectDatabaseCommand implements \Denosys\Console\LazyCommandInterface
{
    public function __construct(private \Denosys\Database\Connection\Connection $connection) {}

    public static function commandName(): string { return 'database:inspect'; }
    public static function commandDescription(): string { return 'Show the database driver'; }
    public static function commandDefinition(): \Denosys\Console\CommandDefinition
    {
        return new \Denosys\Console\CommandDefinition();
    }

    public function getName(): string { return self::commandName(); }
    public function getDescription(): string { return self::commandDescription(); }
    public function configure(): \Denosys\Console\CommandDefinition { return self::commandDefinition(); }

    public function execute(
        \Denosys\Console\InputInterface $input,
        \Denosys\Console\OutputInterface $output,
    ): int {
        $output->info($this->connection->getDriverName());
        return 0;
    }
}
```

Keep the instance methods delegated to the static metadata so listing and
execution expose the same command contract.

## Development

composer validate --strict
find src -type f -name '*.php' -print0 | xargs -0 -n1 php -l
composer test

## CI Workflows

- CI: composer validation, dependency install, PHPUnit tests, and PHP syntax
  lint on push and pull requests.
- Release: GitHub release publication on semantic version tags.
- Dependabot: weekly Composer dependency update checks.
