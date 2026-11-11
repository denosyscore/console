# Changelog

## 0.4.2 – 2026-11-11

- Accept `denosyscore/http` 0.4, which removes classes this package never used.
  CI tests it with database 0.5.0 and queue 0.3.3.

## 0.4.1 – 2026-11-11

- Accept `denosyscore/database` 0.5. The test suite passes against 0.5.0, and CI
  tests it alongside the matching HTTP and dependent package releases.

## 0.4.0

- Generate Symfony MIME `Email` messages that work with the framework's
  `MailerInterface` binding, and emit a valid PHP HTML view template for
  `--view`.
- Remove `--markdown` and the unusable `Denosys\Mail\Mailable` stubs. This
  breaks the command's previous output and option shape; existing generated
  files require a manual conversion. Console's runtime command interfaces
  and queue behavior are unchanged.
- Test the generated class and template by loading and using them.

## 0.3.2

- Accept database 0.4 while retaining the supported 0.2 and 0.3 lines.
- Test database 0.4 with compatible HTTP and queue releases in CI.

## 0.3.1

- Accept database and HTTP 0.3 while retaining support for their 0.2 lines.
- Test published old and new dependency combinations on PHP 8.2 and 8.5.

## 0.3.0

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
