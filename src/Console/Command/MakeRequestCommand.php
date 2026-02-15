<?php

declare(strict_types=1);

namespace Denosys\Console\Command;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;

/**
 * Generate a new form request.
 */
class MakeRequestCommand implements CommandInterface
{
    use GeneratorTrait;

    public function __construct(string $basePath)
    {
        $this->initializeGenerator($basePath);
    }

    public function getName(): string
    {
        return 'make:request';
    }

    public function getDescription(): string
    {
        return 'Create a new form request class';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addArgument('name', CommandDefinition::ARGUMENT_REQUIRED, 'The name of the request')
            ->addOption('force', 'f', CommandDefinition::OPTION_NONE, 'Overwrite if exists')
            ->setHelp(<<<HELP
Creates a new form request class for validation.

Examples:
  php denosys make:request StoreUserRequest
  php denosys make:request Api/UpdateProfileRequest
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $force = $input->getOption('force');

        // Parse name into namespace and class
        $parts = explode('/', str_replace('\\', '/', $name));
        $className = $this->studly(array_pop($parts));
        
        // Ensure Request suffix
        if (!str_ends_with($className, 'Request')) {
            $className .= 'Request';
        }
        
        $subNamespace = implode('\\', array_map([$this, 'studly'], $parts));
        $namespace = 'App\\Http\\Requests' . ($subNamespace ? '\\' . $subNamespace : '');

        // Ensure directory
        $subPath = str_replace('\\', '/', $subNamespace);
        $this->ensureDirectory($this->basePath . '/app/Http/Requests' . ($subPath ? '/' . $subPath : ''));

        // Generate content
        $content = $this->getStubContent('request.stub', [
            'namespace' => $namespace,
            'class' => $className,
        ]);

        $path = $this->basePath . '/app/Http/Requests' . ($subPath ? '/' . $subPath : '') . "/{$className}.php";

        if (!$this->writeFile($path, $content, $output, $force)) {
            return 1;
        }

        $output->success("Request created: {$className}");
        $output->comment("Path: " . $this->relativePath($path));

        return 0;
    }
}
