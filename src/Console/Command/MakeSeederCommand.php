<?php

declare(strict_types=1);

namespace CFXP\Core\Console\Command;

use CFXP\Core\Console\CommandDefinition;
use CFXP\Core\Console\CommandInterface;
use CFXP\Core\Console\InputInterface;
use CFXP\Core\Console\OutputInterface;

/**
 * Generate a new seeder file.
 */
class MakeSeederCommand implements CommandInterface
{
    use GeneratorTrait;

    public function __construct(string $basePath)
    {
        $this->initializeGenerator($basePath);
    }

    public function getName(): string
    {
        return 'make:seeder';
    }

    public function getDescription(): string
    {
        return 'Create a new database seeder';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addArgument('name', CommandDefinition::ARGUMENT_REQUIRED, 'The seeder name')
            ->setHelp(<<<HELP
Creates a new database seeder file.

Examples:
  php cfxp make:seeder UserSeeder
  php cfxp make:seeder DatabaseSeeder
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        if (empty($name)) {
            $output->error('Please provide a seeder name.');
            return 1;
        }

        // Ensure name ends with Seeder
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $path = $this->basePath . '/database/seeders';
        $this->ensureDirectory($path);

        $fullPath = $path . '/' . $name . '.php';

        if (file_exists($fullPath)) {
            $output->error("Seeder already exists: {$name}");
            return 1;
        }

        $content = $this->getStubContent('seeder.stub', [
            'class' => $name,
        ]);

        file_put_contents($fullPath, $content);

        $output->success("Seeder created: {$name}");
        $output->comment("Path: " . $this->relativePath($fullPath));

        return 0;
    }
}
