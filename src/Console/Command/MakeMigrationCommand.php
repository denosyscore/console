<?php

declare(strict_types=1);

namespace Denosys\Console\Command;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;

/**
 * Generate a new migration file.
 */
class MakeMigrationCommand implements CommandInterface
{
    use GeneratorTrait;

    public function __construct(
        string $basePath,
    ) {
        $this->initializeGenerator($basePath);
    }

    public function getName(): string
    {
        return 'make:migration';
    }

    public function getDescription(): string
    {
        return 'Create a new migration file';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addArgument('name', CommandDefinition::ARGUMENT_REQUIRED, 'The name of the migration')
            ->addOption('create', null, CommandDefinition::OPTION_OPTIONAL, 'The table to be created')
            ->addOption('table', null, CommandDefinition::OPTION_OPTIONAL, 'The table to modify')
            ->setHelp(<<<HELP
Creates a new database migration file.

Examples:
  php core make:migration CreateUsersTable --create=users
  php core make:migration AddEmailToUsers --table=users
  php core make:migration UpdatePaymentSettings

To customize templates, run: php core stubs:publish
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        if (empty($name)) {
            $output->error('Please provide a migration name.');
            return 1;
        }

        $createTable = $input->getOption('create');
        $updateTable = $input->getOption('table');

        // Generate filename with timestamp
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$this->snake($name)}.php";
        
        $path = $this->basePath . '/database/migrations';
        $this->ensureDirectory($path);

        $fullPath = $path . '/' . $filename;

        // Generate content from stub
        if ($createTable) {
            $content = $this->getStubContent('migration.create.stub', ['table' => $createTable]);
        } elseif ($updateTable) {
            $content = $this->getStubContent('migration.update.stub', ['table' => $updateTable]);
        } else {
            $content = $this->getStubContent('migration.stub', []);
        }

        file_put_contents($fullPath, $content);

        $output->success("Migration created: {$filename}");
        $output->comment("Path: " . $this->relativePath($fullPath));

        return 0;
    }
}
