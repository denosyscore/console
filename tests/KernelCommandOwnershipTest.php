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

final class PlainKernel extends Kernel
{
    protected array $coreCommands = [];
    protected array $commandPaths = [];
}

/** The console package ships only its own commands; hosts add theirs. */
final class KernelCommandOwnershipTest extends TestCase
{
    public function testHostsCanAddCommandsTheConsolePackageDoesNotOwn(): void
    {
        $kernel = new PlainKernel(new Container(), sys_get_temp_dir(), [HostOwnedCommand::class]);

        self::assertArrayHasKey('host:check', $kernel->bootstrap()->getCommands());
    }

    public function testCoreCommandsBelongToThisPackage(): void
    {
        $core = (new \ReflectionClass(Kernel::class))->getProperty('coreCommands')->getDefaultValue();

        foreach ($core as $command) {
            self::assertStringStartsWith('Denosys\\Console\\', $command);
            self::assertTrue(class_exists($command), "Core command [{$command}] does not exist.");
        }
    }
}
