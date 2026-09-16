<?php

declare(strict_types=1);

namespace App\Models\Ssh;

/**
 * Verifies a server host key signature over the key-exchange hash, and (optionally)
 * cross-checks the host key against the user's `~/.ssh/known_hosts`.
 *
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class HostKeyVerifier
{
    /**
     * @throws \SodiumException
     */
    public static function verifySignature(string $hostKeyBlob, string $message, string $signatureBlob): bool
    {
        $keyReader = new Reader($hostKeyBlob);
        $keyType = $keyReader->readString();

        $signatureReader = new Reader($signatureBlob);
        $signatureAlgorithm = $signatureReader->readString();
        $signature = $signatureReader->readString();

        return match ($keyType) {
            'ssh-ed25519' => self::verifyEd25519($keyReader, $signature, $message),
            'ssh-rsa' => self::verifyRsa($keyReader, $signatureAlgorithm, $signature, $message),
            default => throw new SshException(sprintf('Unsupported host key type: %s', $keyType)),
        };
    }

    /**
     * Cross-checks a host key against `~/.ssh/known_hosts`. If the host has no recorded
     * entries at all, this is a no-op (trust-on-first-use, without persisting anything).
     * If the host has entries for this key type and none of them matches, this throws -
     * protecting against a key that has changed since it was last recorded.
     */
    public static function checkKnownHosts(string $host, int $port, string $hostKeyBlob): void
    {
        $path = getenv('HOME').'/.ssh/known_hosts';

        if (!file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);

        if (false === $contents) {
            return;
        }

        $keyType = (new Reader($hostKeyBlob))->readString();
        $encodedKey = base64_encode($hostKeyBlob);

        $hostAliases = 22 === $port ? [$host] : [sprintf('[%s]:%d', $host, $port)];

        $hasMatchingHost = false;

        $lines = preg_split('/\r\n|\r|\n/', $contents);

        foreach (false === $lines ? [] : $lines as $line) {
            $line = trim($line);

            if ('' === $line || str_starts_with($line, '#')) {
                continue;
            }

            $fields = preg_split('/\s+/', $line);

            if (false === $fields || count($fields) < 3) {
                continue;
            }

            [$hostPattern, $lineKeyType, $lineKey] = $fields;

            if (!self::hostMatches($hostPattern, $hostAliases)) {
                continue;
            }

            if ($lineKeyType !== $keyType) {
                continue;
            }

            $hasMatchingHost = true;

            if (rtrim($lineKey) === $encodedKey) {
                return;
            }
        }

        if ($hasMatchingHost) {
            throw new SshException(
                sprintf(
                    'Host key for %s:%d does not match the key recorded in ~/.ssh/known_hosts. '
                    .'This could indicate a man-in-the-middle attack. Refusing to continue.',
                    $host,
                    $port
                )
            );
        }
    }

    /**
     * @throws \SodiumException
     */
    private static function verifyEd25519(Reader $keyReader, string $signature, string $message): bool
    {
        $publicKey = $keyReader->readString();

        if ('' === $signature || '' === $publicKey) {
            throw new SshException('Invalid Ed25519 host key or signature.');
        }

        return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
    }

    private static function verifyRsa(
        Reader $keyReader,
        string $signatureAlgorithm,
        string $signature,
        string $message
    ): bool {
        $e = $keyReader->readMpint();
        $n = $keyReader->readMpint();

        $algorithm = match ($signatureAlgorithm) {
            'rsa-sha2-256' => OPENSSL_ALGO_SHA256,
            'rsa-sha2-512' => OPENSSL_ALGO_SHA512,
            'ssh-rsa' => OPENSSL_ALGO_SHA1,
            default => throw new SshException(
                sprintf('Unsupported host key signature algorithm: %s', $signatureAlgorithm)
            ),
        };

        $publicKey = openssl_pkey_get_public(Der::rsaPublicKeyPem($n, $e));

        if (false === $publicKey) {
            throw new SshException('Could not read RSA host key.');
        }

        return 1 === openssl_verify($message, $signature, $publicKey, $algorithm);
    }

    /**
     * @param array<int, string> $hostAliases
     */
    private static function hostMatches(string $hostPattern, array $hostAliases): bool
    {
        if (str_starts_with($hostPattern, '|1|')) {
            return self::hashedHostMatches($hostPattern, $hostAliases);
        }

        foreach (explode(',', $hostPattern) as $pattern) {
            if (in_array($pattern, $hostAliases, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $hostAliases
     */
    private static function hashedHostMatches(string $hostPattern, array $hostAliases): bool
    {
        $parts = explode('|', $hostPattern);

        if (4 !== count($parts) || '1' !== $parts[1]) {
            return false;
        }

        $salt = base64_decode($parts[2], true);
        $expectedHash = base64_decode($parts[3], true);

        if (false === $salt || false === $expectedHash) {
            return false;
        }

        foreach ($hostAliases as $alias) {
            if (hash_equals($expectedHash, hash_hmac('sha1', $alias, $salt, true))) {
                return true;
            }
        }

        return false;
    }
}
