<?php

declare(strict_types=1);

namespace App\Models\Ssh;

/**
 * Minimal DER/PEM helpers, only covering what is needed to bridge raw RSA key
 * material (as carried on the SSH wire / in an OpenSSH private key container)
 * to a PEM structure that PHP's openssl extension can load.
 *
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Der
{
    private const string OID_RSA_ENCRYPTION = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01";

    public static function integer(string $magnitude): string
    {
        $magnitude = ltrim($magnitude, "\x00");

        if ('' === $magnitude) {
            $magnitude = "\x00";
        }

        if (0 !== (ord($magnitude[0]) & 0x80)) {
            $magnitude = "\x00".$magnitude;
        }

        return self::tag(0x02, $magnitude);
    }

    /**
     * @param array<int, string> $parts
     */
    public static function sequence(array $parts): string
    {
        return self::tag(0x30, implode('', $parts));
    }

    public static function bitString(string $bytes): string
    {
        return self::tag(0x03, "\x00".$bytes);
    }

    public static function null(): string
    {
        return "\x05\x00";
    }

    public static function tag(int $tag, string $value): string
    {
        return chr($tag).self::length(strlen($value)).$value;
    }

    public static function toPem(string $der, string $label): string
    {
        return sprintf(
            "-----BEGIN %s-----\n%s-----END %s-----\n",
            $label,
            chunk_split(base64_encode($der), 64),
            $label
        );
    }

    /**
     * Builds a PKCS#1 RSAPrivateKey DER structure, PEM-encoded, from raw big-endian magnitudes.
     */
    public static function rsaPrivateKeyPem(
        string $n,
        string $e,
        string $d,
        string $p,
        string $q,
        string $dp,
        string $dq,
        string $qInv
    ): string {
        $der = self::sequence([
            self::integer("\x00"),
            self::integer($n),
            self::integer($e),
            self::integer($d),
            self::integer($p),
            self::integer($q),
            self::integer($dp),
            self::integer($dq),
            self::integer($qInv),
        ]);

        return self::toPem($der, 'RSA PRIVATE KEY');
    }

    /**
     * Builds an X.509 SubjectPublicKeyInfo DER structure, PEM-encoded, for an RSA public key.
     */
    public static function rsaPublicKeyPem(string $n, string $e): string
    {
        $rsaPublicKey = self::sequence([
            self::integer($n),
            self::integer($e),
        ]);

        $der = self::sequence([
            self::sequence([
                self::OID_RSA_ENCRYPTION,
                self::null(),
            ]),
            self::bitString($rsaPublicKey),
        ]);

        return self::toPem($der, 'PUBLIC KEY');
    }

    private static function length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)).$bytes;
    }
}
