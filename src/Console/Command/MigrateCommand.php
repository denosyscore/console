<?php

declare(strict_types=1);

namespace Denosys\Console\Command;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;
use Denosys\Container\ContainerInterface;
use Denosys\Database\Migration\Migrator;
use Denosys\Database\Seeding\SeederRunner;

/**
 * Run pending database migrations.
 */
class MigrateCommand implements CommandInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ?SeederRunner $seederRunner = null,
    ) {}

    public function getName(): string
    {
        return 'migrate';
    }

    public function getDescription(): string
    {
        return 'Run pending database migrations';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addOption('pretend', null, CommandDefinition::OPTION_NONE, 'Dump the SQL queries without running them')
            ->addOption('force', null, CommandDefinition::OPTION_NONE, 'Force in production')
            ->addOption('seed', null, CommandDefinition::OPTION_OPTIONAL, 'Run seeders after migration (optionally specify class)', false)
            ->setHelp(<<<HELP
Run all pending database migrations.

Examples:
  php core migrate                    # Run migrations
  php core migrate --seed             # Run migrations then DatabaseSeeder
  php core migrate --seed=UserSeeder  # Run migrations then specific seeder
  php core migrate --pretend          # Show SQL without running
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $pretend = $input->getOption('pretend');
        $force = $input->getOption('force');
        $seed = $input->getOption('seed');

        // Safety check for production
        if (!$force && $this->isProduction()) {
            if (!$output->confirm('Are you sure you want to run migrations in production?')) {
                $output->warning('Migration cancelled.');
                return 0;
            }
        }

        $output->info('Running migrations...');

        if ($pretend) {
            $this->migrator()->pretend();
        }

        $result = $this->migrator()->run();

        if ($result->isEmpty()) {
            $output->comment('Nothing to migrate.');
        } else {
            foreach ($result->getMigrations() as $record) {
                $time = number_format($record->time * 1000, 2);
                
                if ($record->success) {
                    $output->writeln("<info>  ✓ {$record->name}</info> ({$time}ms)");
                } else {
                    $output->writeln("<error>  ✗ {$record->name}: {$record->error}</error>");
                }
            }

            $output->newLine();
            
            if ($result->isSuccess()) {
                $output->success(sprintf(
                    'Ran %d migration(s) in %.2f seconds.',
                    $result->count(),
                    $result->getTotalTime()
                ));
            } else {
                $output->error('Migration failed: ' . $result->getError());
                return 1;
            }
        }

        // Run seeders if requested (--seed or --seed=ClassName)
        // When --seed passed: null (no value) or string (with value)
        // When not passed: false (default)
        if ($seed !== false) {
            $seederName = is_string($seed) && $seed !== '' ? $seed : null;
            return $this->runSeeders($output, $seederName);
        }

        return 0;
    }

    /**
     * Run database seeders.
     */
    private function runSeeders(OutputInterface $output, ?string $seederName = null): int
    {
        if ($this->seederRunner === null) {
            $output->warning('Seeder runner not available. Run db:seed separately.');
            return 0;
        }

        $output->newLine();
        $output->info('Running seeders...');

        try {
            // Resolve seeder class name
            if ($seederName !== null) {
                $seederClass = str_contains($seederName, '\\') 
                    ? $seederName 
                    : 'Database\\Seeders\\' . $seederName;
            } else {
                $seederClass = 'Database\\Seeders\\DatabaseSeeder';
            }
            
            if (!class_exists($seederClass)) {
                $output->warning("Seeder not found: {$seederClass}");
                return 1;
            }

            $this->seederRunner->run($seederClass);
            
            $output->success('Database seeding completed.');
            return 0;
        } catch (\Throwable $e) {
            $output->error('Seeding failed: ' . $e->getMessage());
            return 1;
        }
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
