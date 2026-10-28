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
before any migration or seeder runs. This command requires the database
package's read-only pretend-mode correction; the development branch in
Composer is temporary until a compatible release is available.

## Development

composer validate --strict
find src -type f -name '*.php' -print0 | xargs -0 -n1 php -l
composer test

## CI Workflows

- CI: composer validation, dependency install, PHPUnit tests, and PHP syntax
  lint on push and pull requests.
- Release: GitHub release publication on semantic version tags.
- Dependabot: weekly Composer dependency update checks.
