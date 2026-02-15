<?php

declare(strict_types=1);

namespace Denosys\Console\Command;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;

/**
 * Generate a new controller.
 */
class MakeControllerCommand implements CommandInterface
{
    use GeneratorTrait;

    public function __construct(string $basePath)
    {
        $this->initializeGenerator($basePath);
    }

    public function getName(): string
    {
        return 'make:controller';
    }

    public function getDescription(): string
    {
        return 'Create a new controller class';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addArgument('name', CommandDefinition::ARGUMENT_REQUIRED, 'The name of the controller')
            ->addOption('resource', 'r', CommandDefinition::OPTION_NONE, 'Generate a resource controller with CRUD methods')
            ->addOption('api', null, CommandDefinition::OPTION_NONE, 'Generate an API controller')
            ->addOption('force', 'f', CommandDefinition::OPTION_NONE, 'Overwrite if exists')
            ->setHelp(<<<HELP
Creates a new controller class.

Examples:
  php core make:controller UserController
  php core make:controller PostController --resource
  php core make:controller Api/V1/ProductController --api
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $isResource = $input->getOption('resource');
        $isApi = $input->getOption('api');
        $force = $input->getOption('force');

        // Determine stub
        if ($isApi) {
            $stubName = 'controller.api.stub';
        } elseif ($isResource) {
            $stubName = 'controller.resource.stub';
        } else {
            $stubName = 'controller.stub';
        }

        // Parse name into namespace and class
        $parts = explode('/', str_replace('\\', '/', $name));
        $className = $this->studly(array_pop($parts));
        
        // Ensure Controller suffix
        if (!str_ends_with($className, 'Controller')) {
            $className .= 'Controller';
        }
        
        $subNamespace = implode('\\', array_map([$this, 'studly'], $parts));
        $namespace = 'App\\Http\\Controllers' . ($subNamespace ? '\\' . $subNamespace : '');
        
        $viewName = $this->kebab(str_replace('Controller', '', $className));

        // Generate content
        $content = $this->getStubContent($stubName, [
            'namespace' => $namespace,
            'class' => $className,
            'view' => $viewName,
        ]);

        // Determine path
        $subPath = str_replace('\\', '/', $subNamespace);
        $path = $this->basePath . '/app/Http/Controllers' . ($subPath ? '/' . $subPath : '') . "/{$className}.php";

        if (!$this->writeFile($path, $content, $output, $force)) {
            return 1;
        }

        $output->success("Controller created: {$className}");
        $output->comment("Path: " . $this->relativePath($path));

        return 0;
    }
}
