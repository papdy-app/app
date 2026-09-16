<?php

declare(strict_types=1);

namespace App\Models\Ssh;

use Random\RandomException;

/**
 * Drives the classic scp sink (`scp -t`) / source (`scp -f`) protocol over a channel
 * running that command on the server, for single regular files only (no recursion,
 * no timestamp preservation) - matching what this application has always needed.
 *
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
readonly class ScpClient
{
    public function __construct(private Transport $transport) {}

    /**
     * @throws RandomException
     */
    public function upload(string $localPath, string $remotePath): void
    {
        $contents = file_get_contents($localPath);

        if (false === $contents) {
            throw new SshException(sprintf('Could not read local file: %s', $localPath));
        }

        $channel = new Channel($this->transport);
        $channel->open();
        $channel->requestExec(sprintf('scp -t %s', escapeshellarg($remotePath)));

        $this->expectAck($channel);

        $channel->write(sprintf("C0644 %d %s\n", strlen($contents), basename($remotePath)));
        $this->expectAck($channel);

        $channel->write($contents);
        $channel->write("\x00");
        $this->expectAck($channel);

        $channel->sendEof();
        $channel->awaitClose();
    }

    /**
     * @throws RandomException
     */
    public function download(string $remotePath, string $localPath): void
    {
        $channel = new Channel($this->transport);
        $channel->open();
        $channel->requestExec(sprintf('scp -f %s', escapeshellarg($remotePath)));

        $channel->write("\x00");

        $header = trim($channel->readLine());

        if (!str_starts_with($header, 'C')) {
            throw new SshException(sprintf('Unexpected scp response for %s: %s', $remotePath, $header));
        }

        $parts = preg_split('/\s+/', $header, 3);

        if (false === $parts || 3 !== count($parts)) {
            throw new SshException(sprintf('Could not parse scp file header: %s', $header));
        }

        $size = (int) $parts[1];

        $channel->write("\x00");

        $contents = $channel->readExact($size);
        $this->expectAck($channel);

        $channel->write("\x00");
        $channel->sendEof();
        $channel->awaitClose();

        if (false === file_put_contents($localPath, $contents)) {
            throw new SshException(sprintf('Could not write local file: %s', $localPath));
        }
    }

    /**
     * @throws RandomException
     */
    private function expectAck(Channel $channel): void
    {
        $status = $channel->readByte();

        if (0 === $status) {
            return;
        }

        $message = trim($channel->readLine());

        throw new SshException(sprintf('scp error: %s', '' !== $message ? $message : sprintf('status %d', $status)));
    }
}
