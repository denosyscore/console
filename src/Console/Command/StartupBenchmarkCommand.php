<?php

declare(strict_types=1);

namespace Denosys\Console\Command;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;
use Denosys\Container\ContainerInterface;
use RuntimeException;

class StartupBenchmarkCommand implements CommandInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function getName(): string
    {
        return 'benchmark:startup';
    }

    public function getDescription(): string
    {
        return 'Benchmark framework startup time and compare cache impact';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addOption('runs', null, CommandDefinition::OPTION_OPTIONAL, 'Measured runs per scenario', '20')
            ->addOption('warmups', null, CommandDefinition::OPTION_OPTIONAL, 'Warmup runs per scenario', '3')
            ->addOption('command', null, CommandDefinition::OPTION_OPTIONAL, 'Command to benchmark (without "php denosys")', 'list --raw')
            ->addOption('entry', null, CommandDefinition::OPTION_OPTIONAL, 'Path to console entry script', 'denosys')
            ->addOption('compare-cache', null, CommandDefinition::OPTION_NONE, 'Run uncached and cached scenarios back-to-back')
            ->addOption('min-improvement', null, CommandDefinition::OPTION_OPTIONAL, 'Require at least this % improvement when using --compare-cache')
            ->addOption('max-average-ms', null, CommandDefinition::OPTION_OPTIONAL, 'Fail if any scenario average exceeds this value (ms)')
            ->addOption('max-p95-ms', null, CommandDefinition::OPTION_OPTIONAL, 'Fail if any scenario p95 exceeds this value (ms)')
            ->addOption('output', null, CommandDefinition::OPTION_OPTIONAL, 'Write results to .json or .csv file')
            ->setHelp(<<<HELP
Benchmark framework startup time by running the CLI in a separate process.

Examples:
  php denosys benchmark:startup
  php denosys benchmark:startup --compare-cache
  php denosys benchmark:startup --runs=30 --warmups=5 --command="list --raw"
  php denosys benchmark:startup --compare-cache --min-improvement=3.0
  php denosys benchmark:startup --compare-cache --max-average-ms=120 --max-p95-ms=180
  php denosys benchmark:startup --compare-cache --output=storage/core/cache/startup-benchmark.json
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $rawRuns = $input->getOption('runs');
        $rawWarmups = $input->getOption('warmups');
        $runs = max(1, (int) (($rawRuns === null || $rawRuns === '') ? 20 : $rawRuns));
        $warmups = max(0, (int) (($rawWarmups === null || $rawWarmups === '') ? 3 : $rawWarmups));
        $compareCache = (bool) $input->getOption('compare-cache');
        $targetCommand = $this->normalizeTargetCommand((string) ($input->getOption('command') ?: 'list --raw'));
        $rawOutputPath = trim((string) ($input->getOption('output') ?: ''));
        $outputPath = $rawOutputPath !== '' ? $rawOutputPath : null;
        $minImprovement = $this->parseOptionalFloat($input->getOption('min-improvement'));
        $maxAverageMs = $this->parseOptionalFloat($input->getOption('max-average-ms'));
        $maxP95Ms = $this->parseOptionalFloat($input->getOption('max-p95-ms'));

        if ($targetCommand === []) {
            $output->error('Target command is empty. Provide a valid command with --command.');
            return 1;
        }

        $basePath = $this->resolveBasePath();
        $entryScript = $this->resolveEntryScript((string) ($input->getOption('entry') ?: 'denosys'), $basePath);
        $phpBinary = \PHP_BINARY !== '' ? \PHP_BINARY : 'php';

        $output->info(sprintf(
            'Benchmarking "%s" (%d run(s), %d warmup(s))',
            implode(' ', $targetCommand),
            $runs,
            $warmups
        ));
        $output->comment('Measured time includes process startup and framework boot.');

        try {
            if ($compareCache) {
                $results = $this->runCacheComparison(
                    $basePath,
                    $entryScript,
                    $phpBinary,
                    $targetCommand,
                    $warmups,
                    $runs,
                    $output
                );
            } else {
                $result = $this->benchmarkScenario(
                    name: 'current',
                    basePath: $basePath,
                    entryScript: $entryScript,
                    phpBinary: $phpBinary,
                    command: $targetCommand,
                    warmups: $warmups,
                    runs: $runs,
                );

                $results = [$result];
                $this->renderSummaryTable($results, $output);
                $output->success('Startup benchmark complete.');
            }

            if ($outputPath !== null) {
                $exportPath = $this->exportResults(
                    $results,
                    $outputPath,
                    $basePath,
                    $targetCommand,
                    $runs,
                    $warmups,
                    $compareCache
                );
                $output->info("Benchmark results exported to {$exportPath}");
            }

            $this->enforceThresholds($results, $compareCache, $minImprovement, $maxAverageMs, $maxP95Ms);

            return 0;
        } catch (RuntimeException $e) {
            $output->error($e->getMessage());
            return 1;
        }
    }

    /**
     * @param array<int, string> $targetCommand
     * @return array<int, array{name: string, runs: int, average_ms: float, median_ms: float, p95_ms: float, min_ms: float, max_ms: float}>
     */
    private function runCacheComparison(
        string $basePath,
        string $entryScript,
        string $phpBinary,
        array $targetCommand,
        int $warmups,
        int $runs,
        OutputInterface $output
    ): array {
        $output->newLine();
        $output->info('Scenario: uncached');
        $this->runMaintenanceCommands(
            ['config:clear', 'routes:clear', 'container:clear'],
            $basePath,
            $entryScript,
            $phpBinary
        );

        $uncached = $this->benchmarkScenario(
            name: 'uncached',
            basePath: $basePath,
            entryScript: $entryScript,
            phpBinary: $phpBinary,
            command: $targetCommand,
            warmups: $warmups,
            runs: $runs,
        );

        $output->newLine();
        $output->info('Scenario: cached');
        $this->runMaintenanceCommands(
            ['config:cache', 'routes:cache', 'container:warmup'],
            $basePath,
            $entryScript,
            $phpBinary
        );

        $cached = $this->benchmarkScenario(
            name: 'cached',
            basePath: $basePath,
            entryScript: $entryScript,
            phpBinary: $phpBinary,
            command: $targetCommand,
            warmups: $warmups,
            runs: $runs,
        );

        $this->renderSummaryTable([$uncached, $cached], $output);

        $improvement = $this->calculateImprovement($uncached['average_ms'], $cached['average_ms']);
        $output->success(sprintf('Average startup improvement with caches: %.2f%%', $improvement));

        return [$uncached, $cached];
    }

    /**
     * @param array<int, array{name: string, runs: int, average_ms: float, median_ms: float, p95_ms: float, min_ms: float, max_ms: float}> $results
     * @param array<int, string> $targetCommand
     */
    private function exportResults(
        array $results,
        string $outputPath,
        string $basePath,
        array $targetCommand,
        int $runs,
        int $warmups,
        bool $compareCache
    ): string {
        $resolvedPath = $this->resolveOutputPath($outputPath, $basePath);
        $directory = dirname($resolvedPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = strtolower((string) pathinfo($resolvedPath, PATHINFO_EXTENSION));

        if ($extension === 'json') {
            $payload = [
                'generated_at' => date(DATE_ATOM),
                'command' => implode(' ', $targetCommand),
                'runs' => $runs,
                'warmups' => $warmups,
                'compare_cache' => $compareCache,
                'scenarios' => $results,
            ];

            $encoded = json_encode($payload, JSON_PRETTY_PRINT);
            if ($encoded === false || file_put_contents($resolvedPath, $encoded . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException("Unable to write benchmark output to {$resolvedPath}");
            }

            return $resolvedPath;
        }

        if ($extension === 'csv') {
            $handle = fopen($resolvedPath, 'wb');
            if ($handle === false) {
                throw new RuntimeException("Unable to write benchmark output to {$resolvedPath}");
            }

            fputcsv($handle, ['scenario', 'runs', 'average_ms', 'median_ms', 'p95_ms', 'min_ms', 'max_ms'], ',', '"', '\\');

            foreach ($results as $result) {
                fputcsv($handle, [
                    $result['name'],
                    (string) $result['runs'],
                    sprintf('%.6f', $result['average_ms']),
                    sprintf('%.6f', $result['median_ms']),
                    sprintf('%.6f', $result['p95_ms']),
                    sprintf('%.6f', $result['min_ms']),
                    sprintf('%.6f', $result['max_ms']),
                ], ',', '"', '\\');
            }

            fclose($handle);
            return $resolvedPath;
        }

        throw new RuntimeException('Unsupported output format. Use a .json or .csv file extension.');
    }

    /**
     * @param array<int, string> $commands
     */
    private function runMaintenanceCommands(
        array $commands,
        string $basePath,
        string $entryScript,
        string $phpBinary
    ): void {
        foreach ($commands as $command) {
            $result = $this->runProcess(
                array_merge([$phpBinary, $entryScript], [$command]),
                $basePath
            );

            if ($result['exit_code'] !== 0) {
                throw new RuntimeException(sprintf(
                    'Failed while running "%s": %s',
                    $command,
                    $result['stderr'] !== '' ? trim($result['stderr']) : trim($result['stdout'])
                ));
            }
        }
    }

    /**
     * @param array<int, string> $command
     * @return array{
     *   name: string,
     *   runs: int,
     *   average_ms: float,
     *   median_ms: float,
     *   p95_ms: float,
     *   min_ms: float,
     *   max_ms: float
     * }
     */
    private function benchmarkScenario(
        string $name,
        string $basePath,
        string $entryScript,
        string $phpBinary,
        array $command,
        int $warmups,
        int $runs
    ): array {
        $processCommand = array_merge([$phpBinary, $entryScript], $command);

        for ($i = 0; $i < $warmups; $i++) {
            $warmupResult = $this->runProcess($processCommand, $basePath);
            if ($warmupResult['exit_code'] !== 0) {
                throw new RuntimeException(sprintf(
                    'Warmup run failed for scenario "%s": %s',
                    $name,
                    $warmupResult['stderr'] !== '' ? trim($warmupResult['stderr']) : trim($warmupResult['stdout'])
                ));
            }
        }

        $durations = [];
        for ($i = 0; $i < $runs; $i++) {
            $result = $this->runProcess($processCommand, $basePath);
            if ($result['exit_code'] !== 0) {
                throw new RuntimeException(sprintf(
                    'Run %d failed for scenario "%s": %s',
                    $i + 1,
                    $name,
                    $result['stderr'] !== '' ? trim($result['stderr']) : trim($result['stdout'])
                ));
            }

            $durations[] = $result['duration_ms'];
        }

        sort($durations);
        $count = count($durations);
        $middleIndex = intdiv($count, 2);
        $median = $count % 2 === 0
            ? ($durations[$middleIndex - 1] + $durations[$middleIndex]) / 2
            : $durations[$middleIndex];
        $p95Index = (int) ceil($count * 0.95) - 1;
        $p95Index = max(0, min($count - 1, $p95Index));

        return [
            'name' => $name,
            'runs' => $count,
            'average_ms' => array_sum($durations) / $count,
            'median_ms' => $median,
            'p95_ms' => $durations[$p95Index],
            'min_ms' => $durations[0],
            'max_ms' => $durations[$count - 1],
        ];
    }

    /**
     * @param array<int, array{name: string, runs: int, average_ms: float, median_ms: float, p95_ms: float, min_ms: float, max_ms: float}> $results
     */
    private function renderSummaryTable(array $results, OutputInterface $output): void
    {
        $rows = [];

        foreach ($results as $result) {
            $rows[] = [
                'Scenario' => $result['name'],
                'Runs' => (string) $result['runs'],
                'Avg (ms)' => number_format($result['average_ms'], 2),
                'Median (ms)' => number_format($result['median_ms'], 2),
                'P95 (ms)' => number_format($result['p95_ms'], 2),
                'Min (ms)' => number_format($result['min_ms'], 2),
                'Max (ms)' => number_format($result['max_ms'], 2),
            ];
        }

        $output->newLine();
        $output->table(
            ['Scenario', 'Runs', 'Avg (ms)', 'Median (ms)', 'P95 (ms)', 'Min (ms)', 'Max (ms)'],
            $rows
        );
    }

    /**
     * @param array<int, string> $command
     * @return array{exit_code: int, stdout: string, stderr: string, duration_ms: float}
     */
    private function runProcess(array $command, string $workingDirectory): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $start = hrtime(true);
        $process = proc_open($command, $descriptorSpec, $pipes, $workingDirectory);

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start benchmark process. Ensure proc_open is enabled.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $durationMs = (hrtime(true) - $start) / 1_000_000;

        return [
            'exit_code' => is_int($exitCode) ? $exitCode : 1,
            'stdout' => $stdout === false ? '' : $stdout,
            'stderr' => $stderr === false ? '' : $stderr,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeTargetCommand(string $raw): array
    {
        $tokens = preg_split('/\s+/', trim($raw)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn(string $token): bool => $token !== ''));

        if ($tokens === []) {
            return [];
        }

        if ($tokens[0] === 'php') {
            array_shift($tokens);
        }

        if ($tokens !== [] && (basename($tokens[0]) === 'denosys' || $tokens[0] === 'denosys')) {
            array_shift($tokens);
        }

        return $tokens;
    }

    private function resolveEntryScript(string $entry, string $basePath): string
    {
        if ($entry === '') {
            return $basePath . '/denosys';
        }

        if (str_starts_with($entry, '/')) {
            return $entry;
        }

        return rtrim($basePath, '/') . '/' . ltrim($entry, '/');
    }

    private function resolveOutputPath(string $outputPath, string $basePath): string
    {
        if ($outputPath === '') {
            throw new RuntimeException('Output path cannot be empty.');
        }

        if (str_starts_with($outputPath, '/')) {
            return $outputPath;
        }

        return rtrim($basePath, '/') . '/' . ltrim($outputPath, '/');
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

    private function calculateImprovement(float $baselineMs, float $optimizedMs): float
    {
        if ($baselineMs <= 0.0) {
            return 0.0;
        }

        return (($baselineMs - $optimizedMs) / $baselineMs) * 100;
    }

    private function parseOptionalFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric((string) $value)) {
            throw new RuntimeException('Benchmark threshold options must be numeric.');
        }

        return (float) $value;
    }

    /**
     * @param array<int, array{name: string, runs: int, average_ms: float, median_ms: float, p95_ms: float, min_ms: float, max_ms: float}> $results
     */
    private function enforceThresholds(
        array $results,
        bool $compareCache,
        ?float $minImprovement,
        ?float $maxAverageMs,
        ?float $maxP95Ms
    ): void {
        if ($maxAverageMs !== null) {
            foreach ($results as $result) {
                if ($result['average_ms'] > $maxAverageMs) {
                    throw new RuntimeException(sprintf(
                        'Benchmark threshold failed: max average %.2fms exceeded by "%s" at %.2fms.',
                        $maxAverageMs,
                        $result['name'],
                        $result['average_ms']
                    ));
                }
            }
        }

        if ($maxP95Ms !== null) {
            foreach ($results as $result) {
                if ($result['p95_ms'] > $maxP95Ms) {
                    throw new RuntimeException(sprintf(
                        'Benchmark threshold failed: max p95 %.2fms exceeded by "%s" at %.2fms.',
                        $maxP95Ms,
                        $result['name'],
                        $result['p95_ms']
                    ));
                }
            }
        }

        if ($minImprovement !== null) {
            if (!$compareCache) {
                throw new RuntimeException('Minimum improvement threshold requires --compare-cache mode.');
            }

            $uncached = $this->findScenario($results, 'uncached');
            $cached = $this->findScenario($results, 'cached');
            $improvement = $this->calculateImprovement($uncached['average_ms'], $cached['average_ms']);

            if ($improvement < $minImprovement) {
                throw new RuntimeException(sprintf(
                    'Benchmark threshold failed: minimum improvement %.2f%% not met (got %.2f%%).',
                    $minImprovement,
                    $improvement
                ));
            }
        }
    }

    /**
     * @param array<int, array{name: string, runs: int, average_ms: float, median_ms: float, p95_ms: float, min_ms: float, max_ms: float}> $results
     * @return array{name: string, runs: int, average_ms: float, median_ms: float, p95_ms: float, min_ms: float, max_ms: float}
     */
    private function findScenario(array $results, string $name): array
    {
        foreach ($results as $result) {
            if ($result['name'] === $name) {
                return $result;
            }
        }

        throw new RuntimeException("Required benchmark scenario [{$name}] was not produced.");
    }
}
