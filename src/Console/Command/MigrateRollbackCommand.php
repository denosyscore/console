<?php

declare(strict_types=1);

namespace CFXP\Core\Console\Command;

use CFXP\Core\Console\CommandDefinition;
use CFXP\Core\Console\CommandInterface;
use CFXP\Core\Console\InputInterface;
use CFXP\Core\Console\OutputInterface;
use CFXP\Core\Container\ContainerInterface;
use CFXP\Core\Database\Migration\Migrator;

/**
 * Rollback database migrations.
 */
class MigrateRollbackCommand implements CommandInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function getName(): string
    {
        return 'migrate:rollback';
    }

    public function getDescription(): string
    {
        return 'Rollback the last batch of migrations';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addOption('step', null, CommandDefinition::OPTION_OPTIONAL, 'Number of batches to rollback', 1)
            ->addOption('force', null, CommandDefinition::OPTION_NONE, 'Force in production');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $steps = (int) ($input->getOption('step') ?: 1);
        $force = $input->getOption('force');

        if (!$force && $this->isProduction()) {
            if (!$output->confirm('Are you sure you want to rollback migrations in production?')) {
                $output->warning('Rollback cancelled.');
                return 0;
            }
        }

        $output->info("Rolling back {$steps} batch(es)...");

        $result = $this->migrator()->rollback($steps);

        if ($result->isEmpty()) {
            $output->comment('Nothing to rollback.');
            return 0;
        }

        foreach ($result->getMigrations() as $record) {
            $time = number_format($record->time * 1000, 2);
            
            if ($record->success) {
                $output->writeln("<info>  ✓ Rolled back: {$record->name}</info> ({$time}ms)");
            } else {
                $output->writeln("<error>  ✗ Failed: {$record->name}: {$record->error}</error>");
            }
        }

        $output->newLine();
        
        if ($result->isSuccess()) {
            $output->success(sprintf(
                'Rolled back %d migration(s) in %.2f seconds.',
                $result->count(),
                $result->getTotalTime()
            ));
            return 0;
        }

        $output->error('Rollback failed: ' . $result->getError());
        return 1;
    }

    private function isProduction(): bool
    {
        $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
        return $env === 'production';
    }

    private function migrator(): Migrator
    {
        /** @var Migrator $migrator */
        $migrator = $this->container->get(Migrator::class);

        return $migrator;
    }
}
