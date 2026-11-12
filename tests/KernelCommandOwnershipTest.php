<?php

declare(strict_types=1);

namespace Tests;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\Kernel;
use Denosys\Console\OutputInterface;
use Denosys\Container\Container;
use PHPUnit\Framework\TestCase;

final class HostOwnedCommand implements CommandInterface
{
    public function getName(): string { return 'host:check'; }
    public function getDescription(): string { return 'A command owned by the host application'; }
    public function configure(): CommandDefinition { return new CommandDefinition(); }
    public function execute(InputInterface $input, OutputInterface $output): int { return 0; }
}

/** The console package ships only its own commands; hosts add theirs. */
final class KernelCommandOwnershipTest extends TestCase
{
    public function testHostsCanAddCommandsTheConsolePackageDoesNotOwn(): void
    {
        $kernel = new Kernel(new Container(), sys_get_temp_dir(), commands: [HostOwnedCommand::class], discoverIn: null, withCoreCommands: false);

        self::assertArrayHasKey('host:check', $kernel->bootstrap()->getCommands());
    }

    public function testCoreCommandsBelongToThisPackage(): void
    {
        $core = (new \ReflectionClass(Kernel::class))->getConstant('CORE_COMMANDS');

        foreach ($core as $command) {
            self::assertStringStartsWith('Denosys\\Console\\', $command);
            self::assertStringNotContainsString('Migrate', $command, 'Database commands belong to the database package.');
            self::assertStringNotContainsString('Queue', $command, 'Queue commands belong to the queue package.');
            self::assertTrue(class_exists($command), "Core command [{$command}] does not exist.");
        }
    }
}
