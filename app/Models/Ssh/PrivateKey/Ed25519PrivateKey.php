<?php

declare(strict_types=1);

namespace App\Models\Ssh\PrivateKey;

use App\Models\Ssh\Buffer;
use App\Models\Ssh\SshException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
readonly class Ed25519PrivateKey implements PrivateKeyInterface
{
    private string $publicKeyBlob;

    /**
     * @param string $secretKey the 64-byte libsodium signing secret key (seed || public key)
     */
    public function __construct(private string $secretKey, string $publicKey)
    {
        if (SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen($this->secretKey)) {
            throw new SshException('Invalid Ed25519 private key length.');
        }

        if (SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen($publicKey)) {
            throw new SshException('Invalid Ed25519 public key length.');
        }

        $this->publicKeyBlob = (new Buffer())->writeString('ssh-ed25519')->writeString($publicKey)->toString();
    }

    public function getAuthenticationAlgorithm(): string
    {
        return 'ssh-ed25519';
    }

    public function getPublicKeyBlob(): string
    {
        return $this->publicKeyBlob;
    }

    /**
     * @throws \SodiumException
     */
    public function sign(string $data): string
    {
        $signature = sodium_crypto_sign_detached($data, $this->secretKey);

        return (new Buffer())->writeString('ssh-ed25519')->writeString($signature)->toString();
    }
}
