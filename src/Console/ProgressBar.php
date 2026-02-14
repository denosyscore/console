<?php

declare(strict_types=1);

namespace CFXP\Core\Console;

/**
 * Progress bar abstraction.
 */
interface ProgressBar
{
    /**
     * Start the progress bar.
     */
    public function start(?int $max = null): void;

    /**
     * Advance the progress bar.
     */
    public function advance(int $step = 1): void;

    /**
     * Set current progress.
     */
    public function setProgress(int $step): void;

    /**
     * Finish the progress bar.
     */
    public function finish(): void;

    /**
     * Set a message.
     */
    public function setMessage(string $message, string $name = 'message'): void;
}
