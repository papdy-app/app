<?php

declare(strict_types=1);

namespace App\Models\Ssh;

use App\Models\Ssh\Agent\AgentClient;
use App\Models\Ssh\PrivateKey\PrivateKeyInterface;
use Random\RandomException;

/**
 * Top-level SSH client: connects and key-exchanges via Transport, authenticates via one of
 * the userauth methods, and exposes plain command execution and SCP file transfer.
 *
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Connection
{
    private readonly Transport $transport;
    private bool $userAuthServiceRequested = false;

    public function __construct()
    {
        $this->transport = new Transport();
    }

    /**
     * @throws RandomException
     * @throws \SodiumException
     */
    public function connect(string $host, int $port, float $timeout = 10.0): void
    {
        $this->transport->connect($host, $port, $timeout);
    }

    /**
     * @throws RandomException
     */
    public function authenticateWithPassword(string $username, string $password): bool
    {
        $this->requestUserAuthService();

        $payload = (new Buffer())->writeByte(MessageType::USERAUTH_REQUEST)->writeString($username)->writeString(
            'ssh-connection'
        )->writeString('password')->writeBoolean(false)->writeString($password)->toString();

        $this->transport->sendPacket($payload);

        return $this->awaitAuthResult();
    }

    /**
     * @throws RandomException
     */
    public function authenticateWithPublicKey(string $username, PrivateKeyInterface $key): bool
    {
        $this->requestUserAuthService();

        $algorithm = $key->getAuthenticationAlgorithm();
        $publicKeyBlob = $key->getPublicKeyBlob();

        $signedData = (new Buffer())->writeString($this->transport->getSessionId())
            ->writeByte(MessageType::USERAUTH_REQUEST)
            ->writeString($username)
            ->writeString('ssh-connection')
            ->writeString('publickey')
            ->writeBoolean(true)
            ->writeString($algorithm)
            ->writeString($publicKeyBlob)
            ->toString();

        $signature = $key->sign($signedData);

        $payload = (new Buffer())->writeByte(MessageType::USERAUTH_REQUEST)
            ->writeString($username)
            ->writeString('ssh-connection')
            ->writeString('publickey')
            ->writeBoolean(true)
            ->writeString($algorithm)
            ->writeString($publicKeyBlob)
            ->writeString($signature)
            ->toString();

        $this->transport->sendPacket($payload);

        return $this->awaitAuthResult();
    }

    /**
     * @throws RandomException
     */
    public function authenticateWithAgent(string $username): bool
    {
        $agent = new AgentClient();

        foreach ($agent->listIdentities() as $identity) {
            if ($this->authenticateWithPublicKey($username, $identity)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws RandomException
     */
    public function exec(string $command, callable $onOutput): void
    {
        $channel = new Channel($this->transport);
        $channel->open();
        $channel->requestExec($command);
        $channel->consumeUntilClosed($onOutput);
    }

    public function scp(): ScpClient
    {
        return new ScpClient($this->transport);
    }

    public function close(): void
    {
        $this->transport->close();
    }

    /**
     * @throws RandomException
     */
    private function requestUserAuthService(): void
    {
        if ($this->userAuthServiceRequested) {
            return;
        }

        $this->transport->sendPacket(
            (new Buffer())->writeByte(MessageType::SERVICE_REQUEST)->writeString('ssh-userauth')->toString()
        );

        $response = $this->transport->readPacket();

        if (MessageType::SERVICE_ACCEPT !== ord($response[0])) {
            throw new SshException('Server refused the ssh-userauth service request.');
        }

        $this->userAuthServiceRequested = true;
    }

    /**
     * @throws RandomException
     */
    private function awaitAuthResult(): bool
    {
        while (true) {
            $payload = $this->transport->readPacket();
            $type = ord($payload[0]);

            if (MessageType::USERAUTH_SUCCESS === $type) {
                return true;
            }

            if (MessageType::USERAUTH_BANNER === $type) {
                continue;
            }

            if (MessageType::USERAUTH_FAILURE === $type) {
                return false;
            }

            throw new SshException(sprintf('Unexpected message type %d during authentication.', $type));
        }
    }
}
