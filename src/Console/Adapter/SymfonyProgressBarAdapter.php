<?php

declare(strict_types=1);

namespace Denosys\Console\Adapter;

use Denosys\Console\ProgressBar;
use Symfony\Component\Console\Helper\ProgressBar as SymfonyProgressBar;

/**
 * Adapter wrapping Symfony's ProgressBar.
 */
class SymfonyProgressBarAdapter implements ProgressBar
{
    public function __construct(
        private readonly SymfonyProgressBar $progressBar,
    ) {}

    public function start(?int $max = null): void
    {
        $this->progressBar->start($max);
    }

    public function advance(int $step = 1): void
    {
        $this->progressBar->advance($step);
    }

    public function setProgress(int $step): void
    {
        $this->progressBar->setProgress($step);
    }

    public function finish(): void
    {
        $this->progressBar->finish();
    }

    public function setMessage(string $message, string $name = 'message'): void
    {
        $this->progressBar->setMessage($message, $name);
    }

    /**
     * Get the underlying Symfony progress bar.
     */
    public function getSymfonyProgressBar(): SymfonyProgressBar
    {
        return $this->progressBar;
    }
}
