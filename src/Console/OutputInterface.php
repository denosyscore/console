<?php

declare(strict_types=1);

namespace CFXP\Core\Console;

/**
 * Abstraction over command output.
 * 
 * Wraps Symfony's OutputInterface to maintain composition pattern.
 */
interface OutputInterface
{
    /**
     * Write a line to output.
      * @param array<string, string> $messages
     */
    public function writeln(string|array $messages): void;

    /**
     * Write to output without newline.
      * @param array<string, string> $messages
     */
    public function write(string|array $messages): void;

    /**
     * Write an info message.
     */
    public function info(string $message): void;

    /**
     * Write a success message.
     */
    public function success(string $message): void;

    /**
     * Write a warning message.
     */
    public function warning(string $message): void;

    /**
     * Write an error message.
     */
    public function error(string $message): void;

    /**
     * Write a comment.
     */
    public function comment(string $message): void;

    /**
     * Write a blank line.
     */
    public function newLine(int $count = 1): void;

    /**
     * Display a table.
      * @param array<string, string|array<string>> $headers
      * @param array<array<string, mixed>> $rows
     */
    public function table(array $headers, array $rows): void;

    /**
     * Ask for confirmation.
     */
    public function confirm(string $question, bool $default = false): bool;

    /**
     * Create a progress bar.
     */
    public function createProgressBar(int $max = 0): ProgressBar;
}
