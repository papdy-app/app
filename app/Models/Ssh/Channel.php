<?php

declare(strict_types=1);

namespace App\Models\Ssh;

use Random\RandomException;

/**
 * A single SSH "session" channel used to run one command (plain exec, or an `scp -t`/`scp -f`
 * subprocess driven byte-for-byte for file transfer). One Channel is used for exactly one
 * command and then closed; this client never multiplexes multiple live channels.
 *
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Channel
{
    private const int WINDOW_SIZE = 2097152;
    private const int MAX_PACKET_SIZE = 32768;

    private static int $nextChannelId = 0;

    private readonly int $localChannelId;
    private int $remoteChannelId = 0;
    private int $remoteWindowSize = 0;
    private int $remoteMaxPacketSize = self::MAX_PACKET_SIZE;
    private int $localWindowSize = self::WINDOW_SIZE;

    private string $readBuffer = '';
    private bool $remoteClosed = false;

    public function __construct(private readonly Transport $transport)
    {
        $this->localChannelId = self::$nextChannelId++;
    }

    /**
     * @throws RandomException
     */
    public function open(): void
    {
        $this->transport->sendPacket(
            (new Buffer())->writeByte(MessageType::CHANNEL_OPEN)->writeString('session')->writeUint32(
                $this->localChannelId
            )->writeUint32(self::WINDOW_SIZE)->writeUint32(self::MAX_PACKET_SIZE)->toString()
        );

        $payload = $this->readControlPacket();
        $type = ord($payload[0]);

        if (MessageType::CHANNEL_OPEN_FAILURE === $type) {
            throw new SshException('Server refused to open a channel.');
        }

        if (MessageType::CHANNEL_OPEN_CONFIRMATION !== $type) {
            throw new SshException('Expected SSH_MSG_CHANNEL_OPEN_CONFIRMATION from server.');
        }

        $reader = new Reader(substr($payload, 1));
        $reader->readUint32(); // recipient channel, i.e. our own local channel id
        $this->remoteChannelId = $reader->readUint32();
        $this->remoteWindowSize = $reader->readUint32();
        $this->remoteMaxPacketSize = $reader->readUint32();
    }

    /**
     * @throws RandomException
     */
    public function requestExec(string $command): void
    {
        $this->transport->sendPacket(
            (new Buffer())->writeByte(MessageType::CHANNEL_REQUEST)->writeUint32($this->remoteChannelId)->writeString(
                'exec'
            )->writeBoolean(true)->writeString($command)->toString()
        );

        $payload = $this->readControlPacket();
        $type = ord($payload[0]);

        if (MessageType::CHANNEL_FAILURE === $type) {
            throw new SshException(sprintf('Server refused to execute: %s', $command));
        }

        if (MessageType::CHANNEL_SUCCESS !== $type) {
            throw new SshException('Expected SSH_MSG_CHANNEL_SUCCESS from server.');
        }
    }

    /**
     * @throws RandomException
     */
    public function write(string $data): void
    {
        $maxChunk = $this->remoteMaxPacketSize > 0 ? $this->remoteMaxPacketSize - 64 : self::MAX_PACKET_SIZE;

        foreach (str_split($data, max(1, $maxChunk)) as $chunk) {
            while ($this->remoteWindowSize < strlen($chunk)) {
                if (!$this->pumpOnce()) {
                    throw new SshException('Channel closed while waiting for window space.');
                }
            }

            $this->transport->sendPacket(
                (new Buffer())->writeByte(MessageType::CHANNEL_DATA)->writeUint32($this->remoteChannelId)->writeString(
                    $chunk
                )->toString()
            );

            $this->remoteWindowSize -= strlen($chunk);
        }
    }

    /**
     * @throws RandomException
     */
    public function sendEof(): void
    {
        $this->transport->sendPacket(
            (new Buffer())->writeByte(MessageType::CHANNEL_EOF)->writeUint32($this->remoteChannelId)->toString()
        );
    }

    /**
     * @throws RandomException
     */
    public function readByte(): int
    {
        return ord($this->readExact(1));
    }

    /**
     * @throws RandomException
     */
    public function readLine(): string
    {
        $line = '';

        do {
            $byte = $this->readExact(1);
            $line .= $byte;
        } while ("\n" !== $byte);

        return $line;
    }

    /**
     * @throws RandomException
     */
    public function readExact(int $length): string
    {
        while (strlen($this->readBuffer) < $length) {
            if (!$this->pumpOnce()) {
                throw new SshException('Channel closed before the expected data was received.');
            }
        }

        $data = substr($this->readBuffer, 0, $length);
        $this->readBuffer = substr($this->readBuffer, $length);

        return $data;
    }

    /**
     * Reads and forwards output until the remote side closes the channel; used for plain
     * (non-SCP) command execution, where the exit code is recovered from the command's own
     * "Exit status: N" text marker rather than the protocol-level channel request.
     *
     * @throws RandomException
     */
    public function consumeUntilClosed(callable $onOutput): void
    {
        while (!$this->remoteClosed) {
            $this->pumpOnce();

            if ('' !== $this->readBuffer) {
                $onOutput($this->readBuffer);
                $this->readBuffer = '';
            }
        }
    }

    /**
     * @throws RandomException
     */
    public function awaitClose(): void
    {
        while (!$this->remoteClosed) {
            $this->pumpOnce();
        }
    }

    /**
     * Reads the next packet for this channel, transparently applying (and swallowing) any
     * SSH_MSG_CHANNEL_WINDOW_ADJUST the server sends - which it may do at any time, including
     * before responding to a channel open/request. Used while awaiting a specific control reply.
     *
     * @throws RandomException
     */
    private function readControlPacket(): string
    {
        while (true) {
            $payload = $this->transport->readPacket();
            $type = ord($payload[0]);

            if (MessageType::CHANNEL_WINDOW_ADJUST === $type) {
                $reader = new Reader(substr($payload, 1));
                $reader->readUint32();
                $this->remoteWindowSize += $reader->readUint32();

                continue;
            }

            return $payload;
        }
    }

    /**
     * Reads and processes exactly one transport packet belonging to this channel.
     * Returns false once the channel has been fully closed (both sides), true otherwise.
     *
     * @throws RandomException
     */
    private function pumpOnce(): bool
    {
        if ($this->remoteClosed) {
            return false;
        }

        $payload = $this->transport->readPacket();
        $type = ord($payload[0]);
        $reader = new Reader(substr($payload, 1));

        switch ($type) {
            case MessageType::CHANNEL_DATA:
                $reader->readUint32();
                $data = $reader->readString();
                $this->readBuffer .= $data;
                $this->adjustLocalWindow(strlen($data));

                break;

            case MessageType::CHANNEL_EXTENDED_DATA:
                $reader->readUint32();
                $reader->readUint32(); // data type code (e.g. stderr)
                $data = $reader->readString();
                $this->readBuffer .= $data;
                $this->adjustLocalWindow(strlen($data));

                break;

            case MessageType::CHANNEL_WINDOW_ADJUST:
                $reader->readUint32();
                $this->remoteWindowSize += $reader->readUint32();

                break;

            case MessageType::CHANNEL_EOF:
                break;

            case MessageType::CHANNEL_CLOSE:
                $this->transport->sendPacket(
                    (new Buffer())->writeByte(MessageType::CHANNEL_CLOSE)
                        ->writeUint32($this->remoteChannelId)
                        ->toString()
                );

                $this->remoteClosed = true;

                return false;

            case MessageType::CHANNEL_REQUEST:
                $reader->readUint32();
                $reader->readString();
                $wantReply = $reader->readBoolean();

                if ($wantReply) {
                    $this->transport->sendPacket(
                        (new Buffer())->writeByte(MessageType::CHANNEL_FAILURE)
                            ->writeUint32($this->remoteChannelId)
                            ->toString()
                    );
                }

                break;

            default:
                throw new SshException(sprintf('Unexpected message type %d on channel.', $type));
        }

        return true;
    }

    /**
     * @throws RandomException
     */
    private function adjustLocalWindow(int $consumed): void
    {
        $this->localWindowSize -= $consumed;

        if ($this->localWindowSize < intdiv(self::WINDOW_SIZE, 2)) {
            $increment = self::WINDOW_SIZE - $this->localWindowSize;

            $this->transport->sendPacket(
                (new Buffer())->writeByte(MessageType::CHANNEL_WINDOW_ADJUST)
                    ->writeUint32($this->remoteChannelId)
                    ->writeUint32($increment)
                    ->toString()
            );

            $this->localWindowSize += $increment;
        }
    }
}
