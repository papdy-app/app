<?php

declare(strict_types=1);

namespace App\Models\Ssh\Agent;

use App\Models\Ssh\PrivateKey\PrivateKeyInterface;
use App\Models\Ssh\Reader;
use App\Models\Ssh\SshException;

/**
 * A key identity held by a running ssh-agent; signing is delegated back to the agent,
 * the private key material itself never leaves it.
 *
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
readonly class AgentIdentity implements PrivateKeyInterface
{
    public function __construct(private AgentClient $agent, private string $blob) {}

    public function getAuthenticationAlgorithm(): string
    {
        $keyType = (new Reader($this->blob))->readString();

        return match ($keyType) {
            'ssh-ed25519' => 'ssh-ed25519',
            'ssh-rsa' => 'rsa-sha2-256',
            default => throw new SshException(sprintf('Unsupported agent key type: %s', $keyType)),
        };
    }

    public function getPublicKeyBlob(): string
    {
        return $this->blob;
    }

    public function sign(string $data): string
    {
        return $this->agent->sign($this->blob, $data);
    }
}
