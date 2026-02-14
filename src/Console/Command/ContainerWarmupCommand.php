<?php

declare(strict_types=1);

namespace CFXP\Core\Console\Command;

use CFXP\Core\Console\CommandDefinition;
use CFXP\Core\Console\CommandInterface;
use CFXP\Core\Console\InputInterface;
use CFXP\Core\Console\OutputInterface;
use CFXP\Core\Container\ContainerInterface;
use RuntimeException;
use Throwable;

class ContainerWarmupCommand implements CommandInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function getName(): string
    {
        return 'container:warmup';
    }

    public function getDescription(): string
    {
        return 'Compile and warm the dependency container cache';
    }

    public function configure(): CommandDefinition
    {
        return new CommandDefinition();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!method_exists($this->container, 'compile')) {
            $output->error('Current container implementation does not support compilation.');
            return 1;
        }

        $cacheFile = $this->resolveContainerCacheFile();
        $start = hrtime(true);
        $status = 'compiled';
        $fingerprint = $this->resolveCurrentFingerprint();

        try {
            $existingMetadata = $this->readCompiledMetadata($cacheFile);
            $existingFingerprint = $existingMetadata['fingerprint'] ?? null;

            if (
                is_string($fingerprint)
                && $fingerprint !== ''
                && is_string($existingFingerprint)
                && hash_equals($existingFingerprint, $fingerprint)
            ) {
                $status = 'up_to_date';
            } else {
                $this->container->compile($cacheFile);
            }

            $this->validateCompiledContainer($cacheFile);
            $this->refreshOpcache($cacheFile);
            $preloadFile = $this->writePreloadScript($cacheFile);
            $compiledMetadata = $this->readCompiledMetadata($cacheFile) ?? [];
            $warmupDurationMs = (hrtime(true) - $start) / 1_000_000;
            $this->writeWarmupMetrics($cacheFile, $status, $warmupDurationMs, $compiledMetadata, $preloadFile, $fingerprint);
        } catch (Throwable $e) {
            $output->error('Container warmup failed: ' . $e->getMessage());
            return 1;
        }

        if ($status === 'up_to_date') {
            $output->info("Container cache already up to date at {$cacheFile}");
        }

        $output->success("Container cache warmed at {$cacheFile}");

        return 0;
    }

    private function resolveContainerCacheFile(): string
    {
        $app = $this->resolveApp();
        if ($app !== null && method_exists($app, 'containerCacheFile')) {
            /** @var string $cacheFile */
            $cacheFile = $app->containerCacheFile();
            return $cacheFile;
        }

        return $this->resolveBasePath() . '/storage/core/cache/container.php';
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

    private function resolveApp(): ?object
    {
        if (!$this->container->has('app')) {
            return null;
        }

        $app = $this->container->get('app');
        return is_object($app) ? $app : null;
    }

    private function validateCompiledContainer(string $cacheFile): void
    {
        if (!is_file($cacheFile)) {
            throw new RuntimeException("Compiled container file was not created: {$cacheFile}");
        }

        $compiledClass = 'CFXP\\Core\\Container\\Compiled\\CompiledContainer';
        if (!class_exists($compiledClass, false)) {
            require_once $cacheFile;
        }

        if (!class_exists($compiledClass) || !is_subclass_of($compiledClass, \CFXP\Core\Container\Container::class)) {
            throw new RuntimeException(
                "Compiled container class [{$compiledClass}] is missing or invalid in {$cacheFile}"
            );
        }
    }

    private function refreshOpcache(string $cacheFile): void
    {
        if (!function_exists('opcache_invalidate') || !$this->isOpcacheEnabled()) {
            return;
        }

        @opcache_invalidate($cacheFile, true);

        if (function_exists('opcache_compile_file')) {
            @opcache_compile_file($cacheFile);
        }
    }

    private function resolveCurrentFingerprint(): ?string
    {
        if (!method_exists($this->container, 'compilationFingerprint')) {
            return null;
        }

        $fingerprint = $this->container->compilationFingerprint();
        return is_string($fingerprint) && $fingerprint !== '' ? $fingerprint : null;
    }

    /**
     * @return array{
     *   fingerprint?: string,
     *   generated_at?: string,
     *   total_bindings?: int,
     *   optimized_bindings?: int,
     *   optimized_classes?: int
     * }|null
     */
    private function readCompiledMetadata(string $cacheFile): ?array
    {
        if (!is_file($cacheFile)) {
            return null;
        }

        $contents = file_get_contents($cacheFile);
        if ($contents === false) {
            return null;
        }

        $metadata = [];

        if (preg_match("/public const FINGERPRINT = '([^']+)'/", $contents, $matches)) {
            $metadata['fingerprint'] = (string) ($matches[1] ?? '');
        }

        if (preg_match("/public const GENERATED_AT = '([^']+)'/", $contents, $matches)) {
            $metadata['generated_at'] = (string) ($matches[1] ?? '');
        }

        if (preg_match('/public const TOTAL_BINDINGS = (\d+);/', $contents, $matches)) {
            $metadata['total_bindings'] = (int) ($matches[1] ?? 0);
        }

        if (preg_match('/public const OPTIMIZED_BINDINGS = (\d+);/', $contents, $matches)) {
            $metadata['optimized_bindings'] = (int) ($matches[1] ?? 0);
        }

        if (preg_match('/public const OPTIMIZED_CLASSES = (\d+);/', $contents, $matches)) {
            $metadata['optimized_classes'] = (int) ($matches[1] ?? 0);
        }

        return $metadata;
    }

    private function writePreloadScript(string $cacheFile): string
    {
        $cacheDirectory = dirname($cacheFile);
        $preloadFile = $cacheDirectory . '/preload.php';

        $script = <<<'PHP'
<?php

declare(strict_types=1);

$cacheFiles = [
    __DIR__ . '/config.php',
    __DIR__ . '/routes.php',
    __DIR__ . '/container.php',
];

if (!function_exists('opcache_compile_file')) {
    return;
}

foreach ($cacheFiles as $cacheFile) {
    if (is_file($cacheFile)) {
        @opcache_compile_file($cacheFile);
    }
}
PHP;

        file_put_contents($preloadFile, $script . PHP_EOL, LOCK_EX);
        chmod($preloadFile, 0644);

        if ($this->isOpcacheEnabled() && function_exists('opcache_compile_file')) {
            @opcache_compile_file($preloadFile);
        }

        return $preloadFile;
    }

    /**
     * @param array<string, mixed> $compiledMetadata
     */
    private function writeWarmupMetrics(
        string $cacheFile,
        string $status,
        float $warmupDurationMs,
        array $compiledMetadata,
        string $preloadFile,
        ?string $requestedFingerprint
    ): void {
        $metricsPath = dirname($cacheFile) . '/container-metrics.json';
        $totalBindings = (int) ($compiledMetadata['total_bindings'] ?? 0);
        $optimizedBindings = (int) ($compiledMetadata['optimized_bindings'] ?? 0);
        $fallbackBindings = max(0, $totalBindings - $optimizedBindings);

        $compileHitRate = $totalBindings > 0 ? ($optimizedBindings / $totalBindings) * 100 : 0.0;
        $fallbackRate = $totalBindings > 0 ? ($fallbackBindings / $totalBindings) * 100 : 0.0;

        $payload = [
            'generated_at' => date(DATE_ATOM),
            'status' => $status,
            'cache_file' => $cacheFile,
            'preload_file' => $preloadFile,
            'fingerprint' => $compiledMetadata['fingerprint'] ?? $requestedFingerprint,
            'total_bindings' => $totalBindings,
            'optimized_bindings' => $optimizedBindings,
            'optimized_classes' => (int) ($compiledMetadata['optimized_classes'] ?? 0),
            'fallback_bindings' => $fallbackBindings,
            'compile_hit_rate' => round($compileHitRate, 4),
            'fallback_rate' => round($fallbackRate, 4),
            'warmup_duration_ms' => round($warmupDurationMs, 4),
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT);
        if (is_string($encoded)) {
            file_put_contents($metricsPath, $encoded . PHP_EOL, LOCK_EX);
            chmod($metricsPath, 0644);
        }
    }

    private function isOpcacheEnabled(): bool
    {
        if (!function_exists('opcache_get_status')) {
            return false;
        }

        $status = @opcache_get_status(false);
        return is_array($status) && (bool) ($status['opcache_enabled'] ?? false);
    }
}
