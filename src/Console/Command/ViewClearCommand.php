<?php

declare(strict_types=1);

namespace Denosys\Console\Command;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;
use Denosys\Config\ConfigurationInterface;

/**
 * Clear compiled view cache files.
 */
class ViewClearCommand implements CommandInterface
{
    public function __construct(
        private readonly ConfigurationInterface $config,
    ) {}

    public function getName(): string
    {
        return 'view:clear';
    }

    public function getDescription(): string
    {
        return 'Clear all compiled view files';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->setHelp(<<<HELP
Clears all compiled view template files from the cache directory.

Examples:
  php cfxp view:clear
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $cachePath = $this->config->get('view.compiled', storage_path('cache/views'));

        if (!is_dir($cachePath)) {
            $output->warning('View cache directory does not exist.');
            return 0;
        }

        $files = glob($cachePath . '/*');
        
        if ($files === false || empty($files)) {
            $output->info('No cached views to clear.');
            return 0;
        }

        $count = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }

        $output->success("Cleared {$count} compiled view file(s).");
        
        return 0;
    }
}
