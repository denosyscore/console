# denosyscore/console

Console kernel and command infrastructure

## Installation

```bash
composer require denosyscore/console
```

## Quick start

Write commands against small DenoSys interfaces and run them on Symfony Console.

<!-- quickstart -->
```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Denosys\Console\Application;
use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;
use Denosys\Container\Container;
use Symfony\Component\Console\Input\ArrayInput;

final class GreetCommand implements CommandInterface
{
    public function getName(): string { return 'greet'; }
    public function getDescription(): string { return 'Greet someone by name'; }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addArgument('name', CommandDefinition::ARGUMENT_REQUIRED, 'Who to greet');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->success("Hello, {$input->getArgument('name')}!");

        return 0;
    }
}

$console = new Application(new Container(), 'My App', '1.0.0');
$console->add(new GreetCommand());

// From a bin script, call $console->run() and use: php bin/console greet Ada
exit($console->getSymfonyApplication()->run(new ArrayInput(['command' => 'greet', 'name' => 'Ada'])));
```

## Included Modules

- src/Console/*
- src/stubs/*

## Mail generator

`make:mail Name` generates an application class extending Symfony MIME's
`Email`, with sender, recipient, and plain-text body constructor arguments.
`make:mail Name --view` also creates a PHP HTML template; render that template
through the application's view engine and pass its result as the body. The
generated class then sets an HTML body. Inject Symfony's `MailerInterface`
from the framework mail provider to send either message:

```php
$mailer->send(new \App\Mail\WelcomeEmail(
    'sender@example.test',
    $recipient,
    'Welcome!',
));
```

An application importing `Symfony\Component\Mime\Email` from generated code
should declare `symfony/mime` as a direct Composer requirement; framework
consumers have the compatible Mailer transport, and must register its mail
provider before sending. The
generator no longer offers `--markdown`: the earlier stub referenced an
undefined `Denosys\Mail\Mailable` and did not have a Markdown renderer.
Existing generated classes should be rewritten as Symfony `Email` messages;
they are not modified automatically.

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

CI tests database 0.2, 0.3, and 0.4 with compatible HTTP and queue
releases on PHP 8.2 and 8.5.

After CI passes on `main`, create an annotated semantic version tag from that
reviewed commit and push the tag. The Release workflow publishes a GitHub
Release. Confirm the matching version is available in the Composer package
index before updating dependent package constraints.
