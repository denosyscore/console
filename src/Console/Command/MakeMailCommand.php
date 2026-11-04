<?php

declare(strict_types=1);

namespace Denosys\Console\Command;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;

/**
 * Generate a Symfony email message class.
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
        return 'Create a new email message class';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addArgument('name', CommandDefinition::ARGUMENT_REQUIRED, 'The name of the email message')
            ->addOption('view', null, CommandDefinition::OPTION_NONE, 'Also create a PHP HTML email template')
            ->addOption('force', 'f', CommandDefinition::OPTION_NONE, 'Overwrite if exists')
            ->setHelp(<<<HELP
Creates a Symfony email message class.

Examples:
  php core make:mail WelcomeEmail
  php core make:mail WelcomeEmail --view         # With view template
  php core make:mail Order/OrderConfirmation     # Nested namespace
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $withView = $input->getOption('view');
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

        // Generate mailable content
        $content = $this->getStubContent('mailable.stub', [
            'namespace' => $namespace,
            'class' => $className,
            'subject' => $this->titleCase($className),
            'body_method' => $withView ? 'html' : 'text',
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
        if ($withView) {
            $output->newLine();
            $output->info("Creating email template...");
            
            $viewDir = $this->basePath . '/resources/views/emails' . ($viewPath ? '/' . $viewPath : '');
            if (!is_dir($viewDir)) {
                mkdir($viewDir, 0755, true);
            }
            
            $templatePath = $viewDir . "/{$viewName}.php";
            $templateContent = $this->getStubContent('email.stub', [
                'class' => $className,
                'subject' => $this->titleCase($className),
            ]);
            
            $this->writeFile($templatePath, $templateContent, $output, $force);
            $output->comment("Template: " . $this->relativePath($templatePath));
        }

        $output->newLine();
        $output->info("Usage:");
        if ($withView) {
            $output->writeln("  \$body = \$views->render('{$fullViewName}');");
        } else {
            $output->writeln("  \$body = 'Your message';");
        }
        $output->writeln("  \$mailer->send(new {$className}('sender@example.test', \$user->email, \$body));");

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
