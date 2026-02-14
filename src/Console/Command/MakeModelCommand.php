<?php

declare(strict_types=1);

namespace Denosys\Console\Command;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;

/**
 * Generate a new model.
 */
class MakeModelCommand implements CommandInterface
{
    use GeneratorTrait;

    public function __construct(string $basePath)
    {
        $this->initializeGenerator($basePath);
    }

    public function getName(): string
    {
        return 'make:model';
    }

    public function getDescription(): string
    {
        return 'Create a new model class';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addArgument('name', CommandDefinition::ARGUMENT_REQUIRED, 'The name of the model')
            ->addOption('migration', 'm', CommandDefinition::OPTION_NONE, 'Also create a migration')
            ->addOption('controller', 'c', CommandDefinition::OPTION_NONE, 'Also create a controller')
            ->addOption('resource', 'r', CommandDefinition::OPTION_NONE, 'Also create a resource controller')
            ->addOption('all', 'a', CommandDefinition::OPTION_NONE, 'Create model, migration, and resource controller')
            ->addOption('force', 'f', CommandDefinition::OPTION_NONE, 'Overwrite if exists')
            ->setHelp(<<<HELP
Creates a new model class.

Examples:
  php cfxp make:model Post
  php cfxp make:model Post -m              # With migration
  php cfxp make:model Post -mc             # With migration and controller
  php cfxp make:model Post --all           # Model, migration, resource controller
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $withMigration = $input->getOption('migration') || $input->getOption('all');
        $withController = $input->getOption('controller') || $input->getOption('all');
        $withResource = $input->getOption('resource') || $input->getOption('all');
        $force = $input->getOption('force');

        // Parse name
        $parts = explode('/', str_replace('\\', '/', $name));
        $className = $this->studly(array_pop($parts));
        
        $subNamespace = implode('\\', array_map([$this, 'studly'], $parts));
        $namespace = 'App\\Models' . ($subNamespace ? '\\' . $subNamespace : '');
        
        $tableName = $this->tableize($className);

        // Generate model content
        $content = $this->getStubContent('model.stub', [
            'namespace' => $namespace,
            'class' => $className,
            'table' => $tableName,
        ]);

        // Determine path
        $subPath = str_replace('\\', '/', $subNamespace);
        $path = $this->basePath . '/app/Models' . ($subPath ? '/' . $subPath : '') . "/{$className}.php";

        if (!$this->writeFile($path, $content, $output, $force)) {
            return 1;
        }

        $output->success("Model created: {$className}");
        $output->comment("Path: " . $this->relativePath($path));

        // Create migration if requested
        if ($withMigration) {
            $output->newLine();
            $output->info("Creating migration...");
            
            $timestamp = date('Y_m_d_His');
            $migrationName = "create_{$tableName}_table";
            $migrationFile = "{$timestamp}_{$migrationName}.php";
            $migrationPath = $this->basePath . "/database/migrations/{$migrationFile}";
            
            $migrationContent = $this->getStubContent('migration.create.stub', [
                'table' => $tableName,
            ]);
            
            $this->writeFile($migrationPath, $migrationContent, $output, $force);
            $output->comment("Migration: " . $this->relativePath($migrationPath));
        }

        // Create controller if requested
        if ($withController || $withResource) {
            $output->newLine();
            $output->info("Creating controller...");
            
            $controllerClass = $className . 'Controller';
            $stubName = $withResource ? 'controller.resource.stub' : 'controller.stub';
            
            $controllerContent = $this->getStubContent($stubName, [
                'namespace' => 'App\\Http\\Controllers',
                'class' => $controllerClass,
                'view' => $this->kebab($className),
            ]);
            
            $controllerPath = $this->basePath . "/app/Http/Controllers/{$controllerClass}.php";
            $this->writeFile($controllerPath, $controllerContent, $output, $force);
            $output->comment("Controller: " . $this->relativePath($controllerPath));
        }

        return 0;
    }
}
