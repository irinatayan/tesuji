<?php

declare(strict_types=1);

namespace App\Game\Engines;

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * Symfony Process-based implementation of {@see GtpClient}.
 *
 * Spawns the GTP engine as a long-running subprocess and communicates via
 * stdin/stdout. GTP responses are framed by a terminating blank line
 * ("\n\n"), which this client uses to know when a reply is complete.
 */
final class ProcessGtpClient implements GtpClient
{
    private readonly Process $process;

    private readonly InputStream $stdin;

    private string $buffer = '';

    private bool $closed = false;

    /**
     * @param  list<string>  $command  Command vector, e.g. ['gnugo', '--mode', 'gtp']
     * @param  float  $responseTimeoutSeconds  Maximum time to wait for a single GTP response
     */
    public function __construct(
        array $command = ['gnugo', '--mode', 'gtp'],
        private readonly float $responseTimeoutSeconds = 30.0,
    ) {
        $this->stdin = new InputStream;
        $this->process = new Process($command);
        $this->process->setInput($this->stdin);
        $this->process->setTimeout(null);
        $this->process->start();
    }

    public function send(string $command): string
    {
        if ($this->closed) {
            throw new GtpException('Cannot send on a closed GTP client');
        }

        $this->stdin->write($command."\n");
        $response = $this->readResponse();

        if (str_starts_with($response, '= ')) {
            return substr($response, 2);
        }
        if ($response === '=') {
            return '';
        }
        if (str_starts_with($response, '?')) {
            throw new GtpException("GTP error for '{$command}': ".ltrim(substr($response, 1)));
        }

        throw new GtpException("Unexpected GTP response for '{$command}': {$response}");
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;

        try {
            if ($this->process->isRunning()) {
                $this->stdin->write("quit\n");
                $this->stdin->close();
                $this->process->wait();
            }
        } catch (\Throwable) {
            // Best effort — kill if quit hangs.
        } finally {
            if ($this->process->isRunning()) {
                $this->process->stop(1);
            }
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function readResponse(): string
    {
        $deadline = microtime(true) + $this->responseTimeoutSeconds;

        while (($split = strpos($this->buffer, "\n\n")) === false) {
            if (! $this->process->isRunning() && $this->process->getIncrementalOutput() === '') {
                $err = trim($this->process->getErrorOutput());
                throw new GtpException('GTP process exited'.($err !== '' ? ": {$err}" : ''));
            }
            if (microtime(true) > $deadline) {
                throw new GtpException('Timed out waiting for GTP response');
            }
            $this->buffer .= $this->process->getIncrementalOutput();
            if (strpos($this->buffer, "\n\n") === false) {
                usleep(10_000);
            }
        }

        $response = substr($this->buffer, 0, $split);
        $this->buffer = substr($this->buffer, $split + 2);

        return $response;
    }
}
