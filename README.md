# denosyscore/console

Console kernel and command infrastructure

## Status

Initial extraction snapshot from denosyscore monorepo as of 2026-02-14.

## Installation

composer require denosyscore/console

## Included Modules

- src/Console/*
- src/stubs/*

## Migration preview

`migrate --pretend` lists pending migration names and reports that no changes
were made. It does not compile SQL. Combining `--pretend` with `--seed` fails
before any migration or seeder runs. This command requires database 0.2.3's
read-only pretend-mode correction.

## Database queue worker

`queue:work` executes popped jobs through `denosyscore/queue`'s `JobRunner`.
This supplies the bootstrapped container to `ContainerAwareJob` payloads and
continues to call `handle()` on existing jobs. The runner requires queue 0.3's
runtime-service contract.

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

## Releases

CI tests both published database/HTTP combinations with their compatible
queue releases on PHP 8.2 and 8.5.

After CI passes on `main`, create an annotated semantic version tag from that
reviewed commit and push the tag. The Release workflow publishes a GitHub
Release. Confirm the matching version is available in the Composer package
index before updating dependent package constraints.
