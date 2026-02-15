<?php

declare(strict_types=1);

namespace Denosys\Console\Command;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;
use Denosys\Container\ContainerInterface;
use RuntimeException;

class OptimizeClearCommand implements CommandInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function getName(): string
    {
        return 'optimize:clear';
    }

    public function getDescription(): string
    {
        return 'Clear framework startup caches';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addOption('entry', null, CommandDefinition::OPTION_OPTIONAL, 'Path to console entry script')
            ->setHelp(<<<HELP
Clear framework startup caches in reverse-safe order.

This command runs:
  1. container:clear
  2. routes:clear
  3. config:clear
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = $this->resolveBasePath();
        $entryOption = $input->getOption('entry');
        $entryScript = $this->resolveEntryScript(is_string($entryOption) ? trim($entryOption) : '', $basePath);
        $phpBinary = \PHP_BINARY !== '' ? \PHP_BINARY : 'php';

        $steps = ['container:clear', 'routes:clear', 'config:clear'];

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
                $output->error(sprintf('Optimize clear failed at step "%s": %s', $step, $message));
                return 1;
            }
        }

        $output->success('Framework caches cleared successfully.');

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
            return $this->detectCurrentEntryScript($basePath);
        }

        return $this->toAbsolutePath($entry, $basePath);
    }

    private function detectCurrentEntryScript(string $basePath): string
    {
        $argv = $_SERVER['argv'] ?? null;

        if (is_array($argv) && isset($argv[0]) && is_string($argv[0])) {
            $script = trim($argv[0]);

            if ($script !== '') {
                $resolved = $this->toAbsolutePath($script, $basePath);
                if (is_file($resolved)) {
                    return $resolved;
                }
            }
        }

        return rtrim($basePath, '/') . '/core';
    }

    private function toAbsolutePath(string $path, string $basePath): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
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
