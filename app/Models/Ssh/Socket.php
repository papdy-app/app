<?php

declare(strict_types=1);

namespace App\Models\Ssh;

/**
 * Thin wrapper around a PHP stream socket (TCP or unix domain) that guarantees exact-length reads.
 *
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Socket
{
    /**
     * @param resource $stream
     */
    private function __construct(private $stream) {}

    public static function connectTcp(string $host, int $port, float $timeout = 10.0): self
    {
        $errorNumber = 0;
        $errorMessage = '';

        $stream = @stream_socket_client(
            sprintf('tcp://%s:%d', $host, $port),
            $errorNumber,
            $errorMessage,
            $timeout
        );

        if (false === $stream) {
            throw new SshException(
                sprintf('Could not connect to %s:%d: %s', $host, $port, $errorMessage)
            );
        }

        stream_set_timeout($stream, (int) $timeout);

        return new self($stream);
    }

    public static function connectUnix(string $path, float $timeout = 5.0): self
    {
        $errorNumber = 0;
        $errorMessage = '';

        $stream = @stream_socket_client(
            sprintf('unix://%s', $path),
            $errorNumber,
            $errorMessage,
            $timeout
        );

        if (false === $stream) {
            throw new SshException(
                sprintf('Could not connect to unix socket %s: %s', $path, $errorMessage)
            );
        }

        return new self($stream);
    }

    public function write(string $data): void
    {
        $length = strlen($data);
        $written = 0;

        while ($written < $length) {
            $result = fwrite($this->stream, substr($data, $written));

            if (false === $result || 0 === $result) {
                throw new SshException('Connection closed while writing data.');
            }

            $written += $result;
        }
    }

    public function read(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        $buffer = '';

        while (strlen($buffer) < $length) {
            $remaining = $length - strlen($buffer);
            $chunk = fread($this->stream, $remaining > 0 ? $remaining : 1);

            if (false === $chunk || ('' === $chunk && feof($this->stream))) {
                throw new SshException('Connection closed while reading data.');
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    public function readLine(int $maxLength = 8192): string
    {
        $line = '';

        while (strlen($line) < $maxLength) {
            $byte = $this->read(1);
            $line .= $byte;

            if ("\n" === $byte) {
                break;
            }
        }

        return $line;
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
