# Changelog

## Unreleased (proposed 0.3.0)

- Report migration previews as pending work without claiming migrations ran
  or SQL was compiled. Reject `--seed` during a preview.
- Consume the database package's read-only pretend-mode correction; replace
  the temporary branch with a compatible patch release before merging.
- Add a migration preview regression test and CI gate.
- Add optional `LazyCommandInterface` metadata so commands can be listed and
  documented without resolving execution-only constructor dependencies.
  Existing commands retain their current registration behavior.
- Add PHPUnit regression tests and a CI test gate for lazy and legacy command
  registration.
