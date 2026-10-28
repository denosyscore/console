# Changelog

## Unreleased (proposed 0.2.5)

- Report migration previews as pending work without claiming migrations ran
  or SQL was compiled. Reject `--seed` during a preview.
- Consume the database package's read-only pretend-mode correction; replace
  the temporary branch with a compatible patch release before merging.
- Add a migration preview regression test and CI gate.
