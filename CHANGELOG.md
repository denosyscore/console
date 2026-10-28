# Changelog

## Unreleased (proposed 0.3.0)

- Execute database jobs through the queue package's `JobRunner`, supporting
  runtime service resolution while preserving legacy job handling.
- Add a database worker regression test and CI test gate.
- Temporarily consume the queue package's runtime-service branch until its
  compatible minor release is available.
