<?php

declare(strict_types=1);

namespace CFXP\Core\Console\Command;

use CFXP\Core\Console\CommandDefinition;
use CFXP\Core\Console\CommandInterface;
use CFXP\Core\Console\InputInterface;
use CFXP\Core\Console\OutputInterface;

/**
 * Publish stubs for customization.
 */
class StubsPublishCommand implements CommandInterface
{
    private string $sourceDir;
    private string $targetDir;

    /** @var array<string, string[]> Stub groups */
    private const GROUPS = [
        'migration' => ['migration.stub', 'migration.create.stub', 'migration.update.stub'],
        'controller' => ['controller.stub', 'controller.resource.stub', 'controller.api.stub'],
        'model' => ['model.stub'],
        'provider' => ['provider.stub'],
        'command' => ['command.stub'],
        'middleware' => ['middleware.stub'],
        'request' => ['request.stub'],
    ];

    public function __construct(string $basePath)
    {
        $this->sourceDir = dirname(__DIR__, 2) . '/stubs';
        $this->targetDir = $basePath . '/stubs';
    }

    public function getName(): string
    {
        return 'stubs:publish';
    }

    public function getDescription(): string
    {
        return 'Publish stubs to your application for customization';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addArgument('type', CommandDefinition::ARGUMENT_OPTIONAL, 'Type of stubs to publish (migration, controller, model, provider, command, middleware, request, all)')
            ->addOption('force', 'f', CommandDefinition::OPTION_NONE, 'Overwrite existing stubs')
            ->setHelp(<<<HELP
Publishes stubs to your application's stubs/ directory for customization.

Usage:
  php cfxp stubs:publish              # Publish ALL stubs
  php cfxp stubs:publish migration    # Publish only migration stubs
  php cfxp stubs:publish controller   # Publish only controller stubs
  php cfxp stubs:publish model        # Publish only model stub
  php cfxp stubs:publish provider     # Publish only provider stub
  php cfxp stubs:publish command      # Publish only command stub
  php cfxp stubs:publish middleware   # Publish only middleware stub
  php cfxp stubs:publish request      # Publish only request stub

Available types:
  migration   - Migration stubs (blank, create, update)
  controller  - Controller stubs (blank, resource, api)
  model       - Model stub
  provider    - Service provider stub
  command     - Console command stub
  middleware  - Middleware stub
  request     - Form request stub
  all         - All stubs (default)
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('type') ?: 'all';
        $force = $input->getOption('force');

        if (!is_dir($this->sourceDir)) {
            $output->error('Source stubs directory not found.');
            return 1;
        }

        // Validate type
        if ($type !== 'all' && !isset(self::GROUPS[$type])) {
            $output->error("Unknown stub type: {$type}");
            $output->newLine();
            $output->writeln('Available types: ' . implode(', ', array_keys(self::GROUPS)) . ', all');
            return 1;
        }

        // Create target directory
        if (!is_dir($this->targetDir)) {
            mkdir($this->targetDir, 0755, true);
            $output->info("Created: stubs/");
        }

        // Determine which stubs to publish
        if ($type === 'all') {
            $stubsToPublish = array_merge(...array_values(self::GROUPS));
        } else {
            $stubsToPublish = self::GROUPS[$type];
        }

        $published = 0;
        $skipped = 0;

        foreach ($stubsToPublish as $filename) {
            $sourcePath = $this->sourceDir . '/' . $filename;
            $targetPath = $this->targetDir . '/' . $filename;

            if (!file_exists($sourcePath)) {
                $output->warning("  Missing: {$filename}");
                continue;
            }

            if (file_exists($targetPath) && !$force) {
                $output->comment("  Skipped: {$filename} (exists)");
                $skipped++;
                continue;
            }

            copy($sourcePath, $targetPath);
            $output->writeln("<info>  Published:</info> {$filename}");
            $published++;
        }

        $output->newLine();
        
        if ($published > 0) {
            $output->success("Published {$published} stub(s) to stubs/");
        } elseif ($skipped > 0) {
            $output->comment("All stubs already exist. Use --force to overwrite.");
        }
        
        if ($skipped > 0 && $published > 0) {
            $output->comment("Skipped {$skipped} existing stub(s).");
        }

        // Show placeholder help only when publishing all or specific useful info
        if ($type === 'all' && $published > 0) {
            $output->newLine();
            $output->writeln('<comment>Available placeholders:</comment>');
            $output->table(
                ['Placeholder', 'Description'],
                [
                    ['{{ namespace }}', 'Full namespace'],
                    ['{{ class }}', 'Class name'],
                    ['{{ table }}', 'Table name'],
                    ['{{ view }}', 'View name'],
                    ['{{ command_name }}', 'Command name'],
                ]
            );
        }

        return 0;
    }
}
