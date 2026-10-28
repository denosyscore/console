<?php

declare(strict_types=1);

namespace Tests;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\Kernel;
use Denosys\Console\LazyCommandInterface;
use Denosys\Console\OutputInterface;
use Denosys\Container\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class DeferredResource
{
}

final class DeferredCommand implements LazyCommandInterface
{
    public function __construct(private DeferredResource $resource)
    {
    }

    public static function commandName(): string { return 'deferred:check'; }
    public static function commandDescription(): string { return 'Check deferred resolution'; }
    public static function commandDefinition(): CommandDefinition
    {
        return (new CommandDefinition())->addOption('value', null, CommandDefinition::OPTION_REQUIRED, 'A test value');
    }

    public function getName(): string { return self::commandName(); }
    public function getDescription(): string { return self::commandDescription(); }
    public function configure(): CommandDefinition { return self::commandDefinition(); }
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->info($input->getOption('value') === 'ready' ? 'ready' : 'missing');

        return 0;
    }
}

final class DeferredTestKernel extends Kernel
{
    protected array $coreCommands = [];
    protected array $commandPaths = [];
    protected array $commands = [DeferredCommand::class];
}

final class LegacyCommand implements CommandInterface
{
    public function getName(): string { return 'legacy:check'; }
    public function getDescription(): string { return 'Check legacy registration'; }
    public function configure(): CommandDefinition { return new CommandDefinition(); }
    public function execute(InputInterface $input, OutputInterface $output): int { return 0; }
}

final class LegacyTestKernel extends Kernel
{
    protected array $coreCommands = [];
    protected array $commandPaths = [];
    protected array $commands = [LegacyCommand::class];
}

final class LazyCommandRegistrationTest extends TestCase
{
    public function testExistingCommandsRemainRegisteredWithoutMetadata(): void
    {
        $console = new LegacyTestKernel(new Container(), __DIR__)->bootstrap();

        self::assertInstanceOf(LegacyCommand::class, $console->getCommands()['legacy:check']);
    }

    public function testListingAndHelpDoNotResolveAnUnselectedCommandDependency(): void
    {
        $container = new Container();
        $resolutions = 0;
        $container->bind(DeferredResource::class, static function () use (&$resolutions): DeferredResource {
            $resolutions++;

            return new DeferredResource();
        });
        $console = new DeferredTestKernel($container, __DIR__)->bootstrap();
        self::assertSame(0, $resolutions);

        $symfony = $console->getSymfonyApplication();
        $output = new BufferedOutput();
        self::assertSame(0, $symfony->run(new ArrayInput(['command' => 'list']), $output));
        self::assertStringContainsString('deferred:check', $output->fetch());
        self::assertSame(0, $resolutions);

        self::assertSame(0, $symfony->run(new ArrayInput(['command' => 'help', 'command_name' => 'deferred:check']), $output));
        self::assertStringContainsString('--value', $output->fetch());
        self::assertSame(0, $resolutions);

        self::assertSame(0, $symfony->run(new ArrayInput(['command' => 'deferred:check', '--value' => 'ready']), $output));
        self::assertStringContainsString('ready', $output->fetch());
        self::assertSame(1, $resolutions);
    }
}
