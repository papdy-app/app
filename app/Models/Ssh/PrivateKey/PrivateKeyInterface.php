<?php

declare(strict_types=1);

namespace App\Models\Ssh\PrivateKey;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
interface PrivateKeyInterface
{
    /**
     * The SSH public key algorithm name to advertise for a userauth publickey request
     * (e.g. "rsa-sha2-256", "ssh-ed25519"). May differ from the key-type name embedded
     * in the public key blob itself.
     */
    public function getAuthenticationAlgorithm(): string;

    /**
     * The SSH-format public key blob, e.g. `string "ssh-ed25519" || string publicKey`.
     */
    public function getPublicKeyBlob(): string;

    /**
     * Returns the SSH-format signature blob, e.g. `string algorithmName || string signature`.
     */
    public function sign(string $data): string;
}
