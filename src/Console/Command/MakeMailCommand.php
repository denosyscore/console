<?php

declare(strict_types=1);

namespace Denosys\Console\Command;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;

/**
 * Generate a new mailable class.
 */
class MakeMailCommand implements CommandInterface
{
    use GeneratorTrait;

    public function __construct(string $basePath)
    {
        $this->initializeGenerator($basePath);
    }

    public function getName(): string
    {
        return 'make:mail';
    }

    public function getDescription(): string
    {
        return 'Create a new mailable class';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addArgument('name', CommandDefinition::ARGUMENT_REQUIRED, 'The name of the mailable')
            ->addOption('view', null, CommandDefinition::OPTION_NONE, 'Also create an email view template')
            ->addOption('markdown', 'm', CommandDefinition::OPTION_NONE, 'Create with markdown template support')
            ->addOption('force', 'f', CommandDefinition::OPTION_NONE, 'Overwrite if exists')
            ->setHelp(<<<HELP
Creates a new mailable class.

Examples:
  php cfxp make:mail WelcomeEmail
  php cfxp make:mail WelcomeEmail --view         # With view template
  php cfxp make:mail WelcomeEmail --markdown     # With markdown support
  php cfxp make:mail Order/OrderConfirmation     # Nested namespace
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $withView = $input->getOption('view');
        $withMarkdown = $input->getOption('markdown');
        $force = $input->getOption('force');

        // Parse name
        $parts = explode('/', str_replace('\\', '/', $name));
        $className = $this->studly(array_pop($parts));
        
        $subNamespace = implode('\\', array_map([$this, 'studly'], $parts));
        $namespace = 'App\\Mail' . ($subNamespace ? '\\' . $subNamespace : '');
        
        // Generate view name
        $viewName = $this->kebab($className);
        $viewPath = implode('/', array_map([$this, 'kebab'], $parts));
        $fullViewName = 'emails' . ($viewPath ? '.' . str_replace('/', '.', $viewPath) : '') . '.' . $viewName;

        // Choose stub based on options
        $stubName = $withMarkdown ? 'mailable.markdown.stub' : 'mailable.stub';

        // Generate mailable content
        $content = $this->getStubContent($stubName, [
            'namespace' => $namespace,
            'class' => $className,
            'view' => $fullViewName,
            'subject' => $this->titleCase($className),
        ]);

        // Determine path
        $subPath = str_replace('\\', '/', $subNamespace);
        $path = $this->basePath . '/app/Mail' . ($subPath ? '/' . $subPath : '') . "/{$className}.php";

        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!$this->writeFile($path, $content, $output, $force)) {
            return 1;
        }

        $output->success("Mailable created: {$className}");
        $output->comment("Path: " . $this->relativePath($path));

        // Create view template if requested
        if ($withView || $withMarkdown) {
            $output->newLine();
            $output->info("Creating email template...");
            
            $viewDir = $this->basePath . '/resources/views/emails' . ($viewPath ? '/' . $viewPath : '');
            if (!is_dir($viewDir)) {
                mkdir($viewDir, 0755, true);
            }
            
            $templatePath = $viewDir . "/{$viewName}.php";
            $templateStub = $withMarkdown ? 'email.markdown.stub' : 'email.stub';
            
            $templateContent = $this->getStubContent($templateStub, [
                'class' => $className,
                'subject' => $this->titleCase($className),
            ]);
            
            $this->writeFile($templatePath, $templateContent, $output, $force);
            $output->comment("Template: " . $this->relativePath($templatePath));
        }

        $output->newLine();
        $output->info("Usage:");
        $output->writeln("  mailer()->to(\$user->email)->send(new {$className}());");

        return 0;
    }

    /**
     * Convert class name to title case with spaces.
     */
    protected function titleCase(string $value): string
    {
        // Convert WelcomeEmail to "Welcome Email"
        return ucwords(trim(preg_replace('/([A-Z])/', ' $1', $value)));
    }
}
