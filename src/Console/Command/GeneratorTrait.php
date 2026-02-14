<?php

declare(strict_types=1);

namespace CFXP\Core\Console\Command;

use CFXP\Core\Console\CommandDefinition;
use CFXP\Core\Console\CommandInterface;
use CFXP\Core\Console\InputInterface;
use CFXP\Core\Console\OutputInterface;

/**
 * Trait providing common generator functionality.
 * 
 * Use this trait in make:* commands for consistent DX.
 * Composition-based - commands implement CommandInterface and use this trait.
 */
trait GeneratorTrait
{
    protected string $basePath;
    protected string $frameworkStubsDir;
    protected string $appStubsDir;

    /**
     * Initialize generator paths.
     */
    protected function initializeGenerator(string $basePath): void
    {
        $this->basePath = $basePath;
        $this->frameworkStubsDir = dirname(__DIR__, 2) . '/stubs';
        $this->appStubsDir = $basePath . '/stubs';
    }

    /**
     * Get stub content with all replacements applied.
     *
     * @param array<string, string> $replacements
     */
    protected function getStubContent(string $stubName, array $replacements): string
    {
        // Check for user-customized stub first
        $stubPath = $this->appStubsDir . '/' . $stubName;
        
        if (!file_exists($stubPath)) {
            // Fall back to framework default
            $stubPath = $this->frameworkStubsDir . '/' . $stubName;
        }

        if (!file_exists($stubPath)) {
            throw new \RuntimeException("Stub not found: {$stubName}");
        }

        $content = file_get_contents($stubPath);

        // Replace placeholders: {{ name }}, {{ namespace }}, etc.
        foreach ($replacements as $key => $value) {
            $content = str_replace("{{ {$key} }}", $value, $content);
        }

        return $content;
    }

    /**
     * Convert string to StudlyCase (PascalCase).
     */
    protected function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        return str_replace(' ', '', ucwords($value));
    }

    /**
     * Convert string to camelCase.
     */
    protected function camel(string $value): string
    {
        return lcfirst($this->studly($value));
    }

    /**
     * Convert string to snake_case.
     */
    protected function snake(string $value): string
    {
        $value = preg_replace('/([a-z])([A-Z])/', '$1_$2', $value);
        return strtolower(str_replace(['-', ' '], '_', $value));
    }

    /**
     * Convert string to kebab-case.
     */
    protected function kebab(string $value): string
    {
        return str_replace('_', '-', $this->snake($value));
    }

    /**
     * Convert class name to table name (pluralized snake_case).
     */
    protected function tableize(string $className): string
    {
        $snake = $this->snake($className);
        
        // Simple pluralization
        if (str_ends_with($snake, 's')) {
            return $snake;
        }
        if (str_ends_with($snake, 'y')) {
            return substr($snake, 0, -1) . 'ies';
        }
        return $snake . 's';
    }

    /**
     * Ensure directory exists.
     */
    protected function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Write file and report.
     */
    protected function writeFile(string $path, string $content, OutputInterface $output, bool $force = false): bool
    {
        if (file_exists($path) && !$force) {
            $output->error("File already exists: {$path}");
            $output->comment("Use --force to overwrite.");
            return false;
        }

        $this->ensureDirectory(dirname($path));
        file_put_contents($path, $content);
        
        return true;
    }

    /**
     * Get the relative path for display.
     */
    protected function relativePath(string $path): string
    {
        if (str_starts_with($path, $this->basePath)) {
            return ltrim(substr($path, strlen($this->basePath)), '/');
        }
        return $path;
    }
}
