<?php

declare(strict_types=1);

namespace App\Models\Ssh;

/**
 * AES-256-CTR, implemented as repeated AES-ECB block encryption of an incrementing 128-bit
 * counter XORed with the data, rather than via openssl's own "aes-256-ctr" mode - because that
 * mode offers no way to resume a keystream across separate calls at an arbitrary byte offset,
 * and SSH packets are decrypted length-first-then-body in two separate reads.
 *
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class CtrCipher
{
    private const int BLOCK_SIZE = 16;

    private string $counter;

    public function __construct(private readonly string $key, string $iv)
    {
        if (32 !== strlen($this->key)) {
            throw new SshException('AES-256-CTR requires a 32-byte key.');
        }

        if (self::BLOCK_SIZE !== strlen($iv)) {
            throw new SshException('AES-256-CTR requires a 16-byte IV.');
        }

        $this->counter = $iv;
    }

    /**
     * Encryption and decryption are the same operation under CTR mode.
     */
    public function crypt(string $data): string
    {
        $output = '';

        foreach (str_split($data, self::BLOCK_SIZE) as $chunk) {
            $keystream = openssl_encrypt(
                $this->counter,
                'aes-256-ecb',
                $this->key,
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
            );

            if (false === $keystream) {
                throw new SshException('Could not generate AES-CTR keystream block.');
            }

            $output .= $chunk ^ substr($keystream, 0, strlen($chunk));

            $this->incrementCounter();
        }

        return $output;
    }

    private function incrementCounter(): void
    {
        for ($i = self::BLOCK_SIZE - 1; $i >= 0; --$i) {
            $byte = ord($this->counter[$i]);

            if (255 === $byte) {
                $this->counter[$i] = "\x00";

                continue;
            }

            $this->counter[$i] = chr($byte + 1);

            break;
        }
    }
}
