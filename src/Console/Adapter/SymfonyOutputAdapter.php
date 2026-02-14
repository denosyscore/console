<?php

declare(strict_types=1);

namespace CFXP\Core\Console\Adapter;

use CFXP\Core\Console\OutputInterface;
use CFXP\Core\Console\ProgressBar;
use Symfony\Component\Console\Helper\ProgressBar as SymfonyProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface as SymfonyInput;
use Symfony\Component\Console\Output\OutputInterface as SymfonyOutput;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Adapter wrapping Symfony's OutputInterface.
 * 
 * Provides rich console output using Symfony Console under the hood
 * while maintaining our composition-based interface.
 */
class SymfonyOutputAdapter implements OutputInterface
{
    private SymfonyStyle $io;

    public function __construct(
        SymfonyInput $symfonyInput,
        private readonly SymfonyOutput $symfonyOutput,
    ) {
        $this->io = new SymfonyStyle($symfonyInput, $symfonyOutput);
    }

    /**
     * @param array<string, string> $messages
     */
    public function writeln(string|array $messages): void
    {
        $this->symfonyOutput->writeln($messages);
    }

    /**
     * @param array<string, string> $messages
     */
    public function write(string|array $messages): void
    {
        $this->symfonyOutput->write($messages);
    }

    public function info(string $message): void
    {
        $this->io->info($message);
    }

    public function success(string $message): void
    {
        $this->io->success($message);
    }

    public function warning(string $message): void
    {
        $this->io->warning($message);
    }

    public function error(string $message): void
    {
        $this->io->error($message);
    }

    public function comment(string $message): void
    {
        $this->writeln("<comment>{$message}</comment>");
    }

    public function newLine(int $count = 1): void
    {
        $this->io->newLine($count);
    }

    /**
     * @param array<string, string> $headers
     * @param array<array<string, mixed>> $rows
      * @param array<array<string, mixed>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $this->io->table($headers, $rows);
    }

    public function confirm(string $question, bool $default = false): bool
    {
        return $this->io->confirm($question, $default);
    }

    public function createProgressBar(int $max = 0): ProgressBar
    {
        $progressBar = new SymfonyProgressBar($this->symfonyOutput, $max);
        return new SymfonyProgressBarAdapter($progressBar);
    }

    /**
     * Get the SymfonyStyle helper for advanced operations.
     */
    public function getSymfonyStyle(): SymfonyStyle
    {
        return $this->io;
    }

    /**
     * Get the underlying Symfony output.
     */
    public function getSymfonyOutput(): SymfonyOutput
    {
        return $this->symfonyOutput;
    }
}
