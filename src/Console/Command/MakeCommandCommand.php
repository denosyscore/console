<?php

declare(strict_types=1);

namespace Denosys\Console\Command;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;

/**
 * Generate a new console command.
 */
class MakeCommandCommand implements CommandInterface
{
    use GeneratorTrait;

    public function __construct(string $basePath)
    {
        $this->initializeGenerator($basePath);
    }

    public function getName(): string
    {
        return 'make:command';
    }

    public function getDescription(): string
    {
        return 'Create a new console command class';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addArgument('name', CommandDefinition::ARGUMENT_REQUIRED, 'The name of the command class')
            ->addOption('command', null, CommandDefinition::OPTION_OPTIONAL, 'The terminal command name')
            ->addOption('force', 'f', CommandDefinition::OPTION_NONE, 'Overwrite if exists')
            ->setHelp(<<<HELP
Creates a new console command class.

Examples:
  php denosys make:command SendEmailsCommand
  php denosys make:command CleanupCommand --command=cleanup:old-files
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $commandName = $input->getOption('command');
        $force = $input->getOption('force');

        $className = $this->studly($name);
        
        // Ensure Command suffix
        if (!str_ends_with($className, 'Command')) {
            $className .= 'Command';
        }

        // Generate command name if not provided
        if (!$commandName) {
            $baseName = str_replace('Command', '', $className);
            $commandName = 'app:' . $this->kebab($baseName);
        }

        // Ensure app/Console/Commands directory exists
        $this->ensureDirectory($this->basePath . '/app/Console/Commands');

        // Generate content
        $content = $this->getStubContent('command.stub', [
            'namespace' => 'App\\Console\\Commands',
            'class' => $className,
            'command_name' => $commandName,
            'description' => 'TODO: Add description',
        ]);

        $path = $this->basePath . "/app/Console/Commands/{$className}.php";

        if (!$this->writeFile($path, $content, $output, $force)) {
            return 1;
        }

        $output->success("Command created: {$className}");
        $output->comment("Path: " . $this->relativePath($path));
        $output->newLine();
        $output->writeln("Command is auto-discovered. Run it with:");
        $output->writeln("  <info>php denosys {$commandName}</info>");

        return 0;
    }
}
