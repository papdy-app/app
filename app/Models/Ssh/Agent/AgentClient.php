<?php

declare(strict_types=1);

namespace App\Models\Ssh\Agent;

use App\Models\Ssh\Buffer;
use App\Models\Ssh\Reader;
use App\Models\Ssh\Socket;
use App\Models\Ssh\SshException;

/**
 * Minimal ssh-agent protocol client (talks to the socket at $SSH_AUTH_SOCK), supporting
 * only what's needed for authentication: listing identities and requesting a signature.
 *
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class AgentClient
{
    private const int REQUEST_IDENTITIES = 11;
    private const int IDENTITIES_ANSWER = 12;
    private const int SIGN_REQUEST = 13;
    private const int SIGN_RESPONSE = 14;

    private const int RSA_SHA2_256 = 2;

    private Socket $socket;

    public function __construct(?string $socketPath = null)
    {
        $socketPath ??= getenv('SSH_AUTH_SOCK') ?: null;

        if (!is_string($socketPath) || '' === $socketPath) {
            throw new SshException('SSH_AUTH_SOCK is not set; no SSH agent is available.');
        }

        $this->socket = Socket::connectUnix($socketPath);
    }

    /**
     * @return array<int, AgentIdentity>
     */
    public function listIdentities(): array
    {
        [$type, $payload] = $this->request(self::REQUEST_IDENTITIES, '');

        if (self::IDENTITIES_ANSWER !== $type) {
            throw new SshException('SSH agent did not respond with an identity list.');
        }

        $reader = new Reader($payload);
        $count = $reader->readUint32();

        $identities = [];

        for ($i = 0; $i < $count; ++$i) {
            $blob = $reader->readString();
            $reader->readString(); // comment

            $identities[] = new AgentIdentity($this, $blob);
        }

        return $identities;
    }

    public function sign(string $keyBlob, string $data): string
    {
        $keyType = (new Reader($keyBlob))->readString();
        $flags = 'ssh-rsa' === $keyType ? self::RSA_SHA2_256 : 0;

        $payload = (new Buffer())->writeString($keyBlob)->writeString($data)->writeUint32($flags)->toString();

        [$type, $responsePayload] = $this->request(self::SIGN_REQUEST, $payload);

        if (self::SIGN_RESPONSE !== $type) {
            throw new SshException('SSH agent refused to create a signature for the requested key.');
        }

        return (new Reader($responsePayload))->readString();
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function request(int $type, string $payload): array
    {
        $body = chr($type).$payload;
        $this->socket->write(pack('N', strlen($body)).$body);

        /** @var array<int, int> $unpacked */
        $unpacked = unpack('N', $this->socket->read(4));
        $response = $this->socket->read($unpacked[1]);

        return [ord($response[0]), substr($response, 1)];
    }
}
