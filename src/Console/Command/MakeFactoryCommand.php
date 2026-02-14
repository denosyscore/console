<?php

declare(strict_types=1);

namespace CFXP\Core\Console\Command;

use CFXP\Core\Console\CommandDefinition;
use CFXP\Core\Console\CommandInterface;
use CFXP\Core\Console\InputInterface;
use CFXP\Core\Console\OutputInterface;

/**
 * Generate a new factory file.
 */
class MakeFactoryCommand implements CommandInterface
{
    use GeneratorTrait;

    public function __construct(string $basePath)
    {
        $this->initializeGenerator($basePath);
    }

    public function getName(): string
    {
        return 'make:factory';
    }

    public function getDescription(): string
    {
        return 'Create a new model factory';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addArgument('name', CommandDefinition::ARGUMENT_REQUIRED, 'The factory name (e.g. UserFactory)')
            ->addOption('model', 'm', CommandDefinition::OPTION_OPTIONAL, 'The model class to generate for')
            ->setHelp(<<<HELP
Creates a new model factory file.

Examples:
  php cfxp make:factory UserFactory
  php cfxp make:factory UserFactory --model=App\\Models\\User
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        if (empty($name)) {
            $output->error('Please provide a factory name.');
            return 1;
        }

        // Ensure name ends with Factory
        if (!str_ends_with($name, 'Factory')) {
            $name .= 'Factory';
        }

        $modelOption = $input->getOption('model');
        $modelClass = $modelOption ?: $this->guessModelClass($name);
        $modelName = $this->getBaseClassName($modelClass);

        $path = $this->basePath . '/database/factories';
        $this->ensureDirectory($path);

        $fullPath = $path . '/' . $name . '.php';

        if (file_exists($fullPath)) {
            $output->error("Factory already exists: {$name}");
            return 1;
        }

        $content = $this->getStubContent('factory.stub', [
            'class' => $name,
            'modelClass' => $modelClass,
            'modelName' => $modelName,
        ]);

        file_put_contents($fullPath, $content);

        $output->success("Factory created: {$name}");
        $output->comment("Path: " . $this->relativePath($fullPath));

        return 0;
    }

    /**
     * Guess the model class from factory name.
     */
    private function guessModelClass(string $factoryName): string
    {
        $modelName = str_replace('Factory', '', $factoryName);
        return "App\\Models\\{$modelName}";
    }

    /**
     * Get the base class name without namespace.
     */
    private function getBaseClassName(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts);
    }
}
