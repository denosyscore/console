<?php

declare(strict_types=1);

namespace Denosys\Console\Command;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;
use Denosys\Container\ContainerInterface;
use RuntimeException;

class OptimizeCommand implements CommandInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function getName(): string
    {
        return 'optimize';
    }

    public function getDescription(): string
    {
        return 'Build framework startup caches';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addOption('entry', null, CommandDefinition::OPTION_OPTIONAL, 'Path to console entry script', 'cfxp')
            ->setHelp(<<<HELP
Build framework startup caches in the recommended order.

This command runs:
  1. config:cache
  2. routes:cache
  3. container:warmup
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = $this->resolveBasePath();
        $entryScript = $this->resolveEntryScript((string) ($input->getOption('entry') ?: 'cfxp'), $basePath);
        $phpBinary = \PHP_BINARY !== '' ? \PHP_BINARY : 'php';

        $steps = ['config:cache', 'routes:cache', 'container:warmup'];

        foreach ($steps as $step) {
            $output->comment("Running {$step}...");

            try {
                $result = $this->runProcess([$phpBinary, $entryScript, $step], $basePath);
            } catch (RuntimeException $e) {
                $output->error($e->getMessage());
                return 1;
            }

            if ($result['exit_code'] !== 0) {
                $message = trim($result['stderr']) !== '' ? trim($result['stderr']) : trim($result['stdout']);
                $output->error(sprintf('Optimize failed at step "%s": %s', $step, $message));
                return 1;
            }
        }

        $output->success('Framework caches optimized successfully.');

        return 0;
    }

    /**
     * @param array<int, string> $command
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function runProcess(array $command, string $workingDirectory): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $workingDirectory);

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start optimize process. Ensure proc_open is enabled.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exit_code' => is_int($exitCode) ? $exitCode : 1,
            'stdout' => $stdout === false ? '' : $stdout,
            'stderr' => $stderr === false ? '' : $stderr,
        ];
    }

    private function resolveEntryScript(string $entry, string $basePath): string
    {
        if ($entry === '') {
            return $basePath . '/cfxp';
        }

        if (str_starts_with($entry, '/')) {
            return $entry;
        }

        return rtrim($basePath, '/') . '/' . ltrim($entry, '/');
    }

    private function resolveBasePath(): string
    {
        if ($this->container->has('path.base')) {
            /** @var string $basePath */
            $basePath = $this->container->get('path.base');
            return rtrim($basePath, '/');
        }

        return getcwd() ?: '.';
    }
}
