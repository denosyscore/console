<?php

declare(strict_types=1);

namespace CFXP\Core\Console\Command;

use CFXP\Core\Console\CommandDefinition;
use CFXP\Core\Console\CommandInterface;
use CFXP\Core\Console\InputInterface;
use CFXP\Core\Console\OutputInterface;

/**
 * Generate a new middleware.
 */
class MakeMiddlewareCommand implements CommandInterface
{
    use GeneratorTrait;

    public function __construct(string $basePath)
    {
        $this->initializeGenerator($basePath);
    }

    public function getName(): string
    {
        return 'make:middleware';
    }

    public function getDescription(): string
    {
        return 'Create a new middleware class';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addArgument('name', CommandDefinition::ARGUMENT_REQUIRED, 'The name of the middleware')
            ->addOption('force', 'f', CommandDefinition::OPTION_NONE, 'Overwrite if exists')
            ->setHelp(<<<HELP
Creates a new middleware class.

Examples:
  php cfxp make:middleware CheckAgeMiddleware
  php cfxp make:middleware Api/EnsureTokenIsValidMiddleware
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $force = $input->getOption('force');

        // Parse name into namespace and class
        $parts = explode('/', str_replace('\\', '/', $name));
        $className = $this->studly(array_pop($parts));
        
        // Ensure Middleware suffix
        if (!str_ends_with($className, 'Middleware')) {
            $className .= 'Middleware';
        }
        
        $subNamespace = implode('\\', array_map([$this, 'studly'], $parts));
        $namespace = 'App\\Http\\Middleware' . ($subNamespace ? '\\' . $subNamespace : '');

        // Ensure directory
        $subPath = str_replace('\\', '/', $subNamespace);
        $this->ensureDirectory($this->basePath . '/app/Http/Middleware' . ($subPath ? '/' . $subPath : ''));

        // Generate content
        $content = $this->getStubContent('middleware.stub', [
            'namespace' => $namespace,
            'class' => $className,
        ]);

        $path = $this->basePath . '/app/Http/Middleware' . ($subPath ? '/' . $subPath : '') . "/{$className}.php";

        if (!$this->writeFile($path, $content, $output, $force)) {
            return 1;
        }

        $output->success("Middleware created: {$className}");
        $output->comment("Path: " . $this->relativePath($path));

        return 0;
    }
}
