# Changelog

## Unreleased (proposed 0.3.0)

- Report migration previews as pending work without claiming migrations ran
  or SQL was compiled. Reject `--seed` during a preview.
- Require database 0.2.3 for read-only migration previews.
- Add a migration preview regression test and CI gate.
- Execute database jobs through the queue package's `JobRunner`, supporting
  runtime service resolution while preserving legacy job handling.
- Add a database worker regression test and CI test gate.
- Require queue 0.3 for runtime service resolution.
- Add optional `LazyCommandInterface` metadata so commands can be listed and
  documented without resolving execution-only constructor dependencies.
  Existing commands retain their current registration behavior.
- Add PHPUnit regression tests and a CI test gate for lazy and legacy command
  registration.
