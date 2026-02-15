<?php

declare(strict_types=1);

namespace Denosys\Console\Command;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;

/**
 * Generate a new service provider.
 */
class MakeProviderCommand implements CommandInterface
{
    use GeneratorTrait;

    public function __construct(string $basePath)
    {
        $this->initializeGenerator($basePath);
    }

    public function getName(): string
    {
        return 'make:provider';
    }

    public function getDescription(): string
    {
        return 'Create a new service provider class';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addArgument('name', CommandDefinition::ARGUMENT_REQUIRED, 'The name of the provider')
            ->addOption('force', 'f', CommandDefinition::OPTION_NONE, 'Overwrite if exists')
            ->setHelp(<<<HELP
Creates a new service provider class.

Examples:
  php denosys make:provider PaymentServiceProvider
  php denosys make:provider EventServiceProvider
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $force = $input->getOption('force');

        $className = $this->studly($name);
        
        // Ensure ServiceProvider suffix
        if (!str_ends_with($className, 'ServiceProvider') && !str_ends_with($className, 'Provider')) {
            $className .= 'ServiceProvider';
        }

        // Generate content
        $content = $this->getStubContent('provider.stub', [
            'namespace' => 'App\\Providers',
            'class' => $className,
        ]);

        $path = $this->basePath . "/app/Providers/{$className}.php";

        if (!$this->writeFile($path, $content, $output, $force)) {
            return 1;
        }

        $output->success("Provider created: {$className}");
        $output->comment("Path: " . $this->relativePath($path));
        $output->newLine();
        $output->writeln("Don't forget to register it in <comment>config/app.php</comment>:");
        $output->writeln("  <info>App\\Providers\\{$className}::class,</info>");

        return 0;
    }
}
