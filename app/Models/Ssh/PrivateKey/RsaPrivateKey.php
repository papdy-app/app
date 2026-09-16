<?php

declare(strict_types=1);

namespace App\Models\Ssh\PrivateKey;

use App\Models\Ssh\Buffer;
use App\Models\Ssh\Der;
use App\Models\Ssh\SshException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
readonly class RsaPrivateKey implements PrivateKeyInterface
{
    private string $publicKeyBlob;

    /**
     * @param \OpenSSLAsymmetricKey $key the loaded private key resource used for signing
     * @param string                $n   raw big-endian modulus, used for the SSH public key blob
     * @param string                $e   raw big-endian public exponent, used for the SSH public key blob
     */
    private function __construct(private \OpenSSLAsymmetricKey $key, string $n, string $e)
    {
        $this->publicKeyBlob = (new Buffer())->writeString('ssh-rsa')->writeMpint($e)->writeMpint($n)->toString();
    }

    public static function fromPem(string $pem): self
    {
        $key = openssl_pkey_get_private($pem);

        if (false === $key) {
            throw new SshException(sprintf('Invalid RSA private key: %s', self::opensslError()));
        }

        $details = openssl_pkey_get_details($key);

        if (false === $details || !isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new SshException('Could not read RSA key parameters.');
        }

        return new self($key, $details['rsa']['n'], $details['rsa']['e']);
    }

    /**
     * Builds an RSA private key from the raw big-endian components as stored in an
     * OpenSSH "openssh-key-v1" private key container (n, e, d, iqmp, p, q).
     *
     * OpenSSH does not guarantee p > q, but the PKCS#1 CRT parameters (and OpenSSL's
     * legacy RSA decoder) require it, so p/q are swapped - and the CRT parameters
     * recomputed from scratch rather than trusting the wire iqmp - when necessary.
     */
    public static function fromComponents(
        string $n,
        string $e,
        string $d,
        string $iqmp,
        string $p,
        string $q
    ): self {
        unset($iqmp);

        $dGmp = self::toGmp($d);
        $pGmp = self::toGmp($p);
        $qGmp = self::toGmp($q);

        if (gmp_cmp($pGmp, $qGmp) < 0) {
            [$pGmp, $qGmp] = [$qGmp, $pGmp];
            [$p, $q] = [$q, $p];
        }

        $qInvGmp = gmp_invert($qGmp, $pGmp);

        if (false === $qInvGmp) {
            throw new SshException('Invalid RSA key: p and q are not coprime.');
        }

        $dp = self::fromGmp(gmp_mod($dGmp, gmp_sub($pGmp, 1)));
        $dq = self::fromGmp(gmp_mod($dGmp, gmp_sub($qGmp, 1)));
        $qInv = self::fromGmp($qInvGmp);

        $pem = Der::rsaPrivateKeyPem($n, $e, $d, $p, $q, $dp, $dq, $qInv);

        $key = openssl_pkey_get_private($pem);

        if (false === $key) {
            throw new SshException(sprintf('Invalid RSA private key: %s', self::opensslError()));
        }

        return new self($key, $n, $e);
    }

    public function getAuthenticationAlgorithm(): string
    {
        return 'rsa-sha2-256';
    }

    public function getPublicKeyBlob(): string
    {
        return $this->publicKeyBlob;
    }

    public function sign(string $data): string
    {
        if (!openssl_sign($data, $signature, $this->key, OPENSSL_ALGO_SHA256)) {
            throw new SshException(sprintf('Could not create RSA signature: %s', self::opensslError()));
        }

        return (new Buffer())->writeString('rsa-sha2-256')->writeString($signature)->toString();
    }

    private static function toGmp(string $bytes): \GMP
    {
        return gmp_import($bytes, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
    }

    private static function fromGmp(\GMP $value): string
    {
        return gmp_export($value, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
    }

    private static function opensslError(): string
    {
        $message = openssl_error_string();

        return false === $message ? 'unknown error' : $message;
    }
}
