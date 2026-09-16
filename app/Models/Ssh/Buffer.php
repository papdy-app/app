<?php

declare(strict_types=1);

namespace App\Models\Ssh;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Buffer
{
    private string $data = '';

    public function writeByte(int $value): static
    {
        $this->data .= chr($value & 0xFF);

        return $this;
    }

    public function writeBoolean(bool $value): static
    {
        return $this->writeByte($value ? 1 : 0);
    }

    public function writeUint32(int $value): static
    {
        $this->data .= pack('N', $value);

        return $this;
    }

    public function writeUint64(int $value): static
    {
        $this->data .= pack('J', $value);

        return $this;
    }

    public function writeString(string $value): static
    {
        return $this->writeUint32(strlen($value))->writeRaw($value);
    }

    /**
     * Encodes a big-endian unsigned magnitude as an SSH mpint (two's complement, minimal length,
     * with a leading zero byte inserted when the most significant bit would otherwise be set).
     */
    public function writeMpint(string $magnitude): static
    {
        $magnitude = ltrim($magnitude, "\x00");

        if ('' === $magnitude) {
            return $this->writeUint32(0);
        }

        if (0 !== (ord($magnitude[0]) & 0x80)) {
            $magnitude = "\x00".$magnitude;
        }

        return $this->writeString($magnitude);
    }

    /**
     * @param array<int, string> $names
     */
    public function writeNameList(array $names): static
    {
        return $this->writeString(implode(',', $names));
    }

    public function writeRaw(string $value): static
    {
        $this->data .= $value;

        return $this;
    }

    public function toString(): string
    {
        return $this->data;
    }
}
