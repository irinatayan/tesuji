<?php

declare(strict_types=1);

namespace App\Game\Engines;

/**
 * Talks to a long-running GTP (Go Text Protocol) subprocess.
 * Implementations are responsible for managing the subprocess lifecycle.
 */
interface GtpClient
{
    /**
     * Send one GTP command and return its response body.
     * For "= body\n\n" replies the return value is "body" (possibly empty).
     * For "?error\n\n" replies an exception is thrown.
     */
    public function send(string $command): string;

    public function close(): void;
}
