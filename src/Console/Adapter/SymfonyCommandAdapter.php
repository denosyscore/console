<?php

declare(strict_types=1);

namespace CFXP\Core\Console\Adapter;

use CFXP\Core\Console\CommandDefinition;
use CFXP\Core\Console\CommandInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface as SymfonyInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface as SymfonyOutput;

/**
 * Adapter that wraps our CommandInterface for Symfony Console.
 * 
 * This is the bridge that allows our composition-based commands
 * to work with Symfony Console under the hood.
 * 
 * User commands implement CommandInterface and never extend Symfony's Command.
 * This adapter handles the inheritance requirement internally.
 */
class SymfonyCommandAdapter extends SymfonyCommand
{
    public function __construct(
        private readonly CommandInterface $command,
    ) {
        parent::__construct($command->getName());
    }

    protected function configure(): void
    {
        $this->setDescription($this->command->getDescription());
        
        $definition = $this->command->configure();
        
        // Map our argument modes to Symfony's
        foreach ($definition->getArguments() as $arg) {
            $mode = $this->mapArgumentMode($arg['mode']);
            $this->addArgument($arg['name'], $mode, $arg['description'], $arg['default']);
        }
        
        // Map our option modes to Symfony's
        foreach ($definition->getOptions() as $opt) {
            $mode = $this->mapOptionMode($opt['mode']);
            $this->addOption($opt['name'], $opt['shortcut'], $mode, $opt['description'], $opt['default']);
        }
        
        if ($definition->getHelp()) {
            $this->setHelp($definition->getHelp());
        }
    }

    protected function execute(SymfonyInput $symfonyInput, SymfonyOutput $symfonyOutput): int
    {
        // Create our adapters
        $input = new SymfonyInputAdapter($symfonyInput);
        $output = new SymfonyOutputAdapter($symfonyInput, $symfonyOutput);
        
        // Execute our command with our abstractions
        return $this->command->execute($input, $output);
    }

    /**
     * Map our argument mode to Symfony's.
     */
    private function mapArgumentMode(int $mode): int
    {
        $symfonyMode = 0;
        
        if ($mode & CommandDefinition::ARGUMENT_REQUIRED) {
            $symfonyMode |= InputArgument::REQUIRED;
        }
        if ($mode & CommandDefinition::ARGUMENT_OPTIONAL) {
            $symfonyMode |= InputArgument::OPTIONAL;
        }
        if ($mode & CommandDefinition::ARGUMENT_IS_ARRAY) {
            $symfonyMode |= InputArgument::IS_ARRAY;
        }
        
        return $symfonyMode ?: InputArgument::OPTIONAL;
    }

    /**
     * Map our option mode to Symfony's.
     */
    private function mapOptionMode(int $mode): int
    {
        $symfonyMode = 0;
        
        if ($mode & CommandDefinition::OPTION_NONE) {
            $symfonyMode |= InputOption::VALUE_NONE;
        }
        if ($mode & CommandDefinition::OPTION_REQUIRED) {
            $symfonyMode |= InputOption::VALUE_REQUIRED;
        }
        if ($mode & CommandDefinition::OPTION_OPTIONAL) {
            $symfonyMode |= InputOption::VALUE_OPTIONAL;
        }
        if ($mode & CommandDefinition::OPTION_IS_ARRAY) {
            $symfonyMode |= InputOption::VALUE_IS_ARRAY;
        }
        
        return $symfonyMode ?: InputOption::VALUE_NONE;
    }
}
