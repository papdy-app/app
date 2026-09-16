<?php

declare(strict_types=1);

namespace App\Models\Ssh\PrivateKey;

use App\Models\Ssh\Reader;
use App\Models\Ssh\SshException;

/**
 * Loads a private key from either a traditional/PKCS#8 PEM blob (delegated to openssl,
 * RSA only) or an OpenSSH "openssh-key-v1" container (RSA or Ed25519, unencrypted only - passphrase-protected
 * keys are not supported).
 *
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class PrivateKeyLoader
{
    private const string OPENSSH_MAGIC = "openssh-key-v1\0";

    public static function load(string $content): PrivateKeyInterface
    {
        $content = trim($content);

        if (str_contains($content, 'BEGIN OPENSSH PRIVATE KEY')) {
            return self::loadOpenSshContainer($content);
        }

        if (str_contains($content, 'PRIVATE KEY')) {
            return RsaPrivateKey::fromPem($content);
        }

        throw new SshException('Unrecognized private key format.');
    }

    private static function loadOpenSshContainer(string $content): PrivateKeyInterface
    {
        $base64 = preg_replace(
            '/-----(BEGIN|END) OPENSSH PRIVATE KEY-----|\s+/',
            '',
            $content
        );

        $decoded = base64_decode((string) $base64, true);

        if (false === $decoded) {
            throw new SshException('Could not decode OpenSSH private key container.');
        }

        $reader = new Reader($decoded);

        if (self::OPENSSH_MAGIC !== $reader->readRaw(strlen(self::OPENSSH_MAGIC))) {
            throw new SshException('Not a valid OpenSSH private key container.');
        }

        $cipherName = $reader->readString();
        $reader->readString(); // kdfname
        $reader->readString(); // kdfoptions

        $numberOfKeys = $reader->readUint32();

        if (1 !== $numberOfKeys) {
            throw new SshException('Only single-key OpenSSH private key files are supported.');
        }

        $reader->readString(); // public key blob (redundant with the private section below)

        if ('none' !== $cipherName) {
            throw new SshException('Passphrase-protected private keys are not supported.');
        }

        $privateSection = new Reader($reader->readString());

        $checkInt1 = $privateSection->readUint32();
        $checkInt2 = $privateSection->readUint32();

        if ($checkInt1 !== $checkInt2) {
            throw new SshException('Could not verify integrity of OpenSSH private key container.');
        }

        $keyType = $privateSection->readString();

        return match ($keyType) {
            'ssh-ed25519' => self::readEd25519($privateSection),
            'ssh-rsa' => self::readRsa($privateSection),
            default => throw new SshException(sprintf('Unsupported private key type: %s', $keyType)),
        };
    }

    private static function readEd25519(Reader $reader): PrivateKeyInterface
    {
        $publicKey = $reader->readString();
        $secretKey = $reader->readString();

        return new Ed25519PrivateKey($secretKey, $publicKey);
    }

    private static function readRsa(Reader $reader): PrivateKeyInterface
    {
        $n = $reader->readMpint();
        $e = $reader->readMpint();
        $d = $reader->readMpint();
        $iqmp = $reader->readMpint();
        $p = $reader->readMpint();
        $q = $reader->readMpint();

        return RsaPrivateKey::fromComponents($n, $e, $d, $iqmp, $p, $q);
    }
}
