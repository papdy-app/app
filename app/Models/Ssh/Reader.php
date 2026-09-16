<?php

declare(strict_types=1);

namespace App\Models\Ssh;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Reader
{
    private int $offset = 0;

    public function __construct(private readonly string $data) {}

    public function readByte(): int
    {
        return ord($this->readRaw(1));
    }

    public function readBoolean(): bool
    {
        return 0 !== $this->readByte();
    }

    public function readUint32(): int
    {
        /** @var array<int, int> $unpacked */
        $unpacked = unpack('N', $this->readRaw(4));

        return $unpacked[1];
    }

    public function readUint64(): int
    {
        /** @var array<int, int> $unpacked */
        $unpacked = unpack('J', $this->readRaw(8));

        return $unpacked[1];
    }

    public function readString(): string
    {
        return $this->readRaw($this->readUint32());
    }

    /**
     * Returns the raw big-endian magnitude, with any leading sign/pad byte stripped.
     */
    public function readMpint(): string
    {
        return ltrim($this->readString(), "\x00");
    }

    /**
     * @return array<int, string>
     */
    public function readNameList(): array
    {
        $list = $this->readString();

        return '' === $list ? [] : explode(',', $list);
    }

    public function readRaw(int $length): string
    {
        if ($length < 0 || $this->offset + $length > strlen($this->data)) {
            throw new SshException('Attempted to read past the end of an SSH buffer.');
        }

        $value = substr($this->data, $this->offset, $length);
        $this->offset += $length;

        return $value;
    }

    public function remaining(): string
    {
        return substr($this->data, $this->offset);
    }

    public function hasRemaining(): bool
    {
        return $this->offset < strlen($this->data);
    }
}
