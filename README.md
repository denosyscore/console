# denosyscore/console

Console kernel and command infrastructure

## Status

Initial extraction snapshot from denosyscore monorepo as of 2026-02-14.

## Installation

composer require denosyscore/console

## Included Modules

- src/Console/*
- src/stubs/*

## Database queue worker

`queue:work` executes popped jobs through `denosyscore/queue`'s `JobRunner`.
This supplies the bootstrapped container to `ContainerAwareJob` payloads and
continues to call `handle()` on existing jobs. The runner requires the queue
package's proposed 0.3 runtime-service contract. The development branch in
Composer is temporary until that contract is released.

## Development

composer validate --strict
find src -type f -name '*.php' -print0 | xargs -0 -n1 php -l
composer test

## CI Workflows

- CI: composer validation, dependency install, PHPUnit tests, and PHP syntax
  lint on push and pull requests.
- Release: GitHub release publication on semantic version tags.
- Dependabot: weekly Composer dependency update checks.
