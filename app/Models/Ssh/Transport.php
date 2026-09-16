<?php

declare(strict_types=1);

namespace App\Models\Ssh;

use Random\RandomException;

/**
 * SSH2 transport layer: version exchange, curve25519-sha256 key exchange, host key
 * verification, and the encrypted (aes256-ctr / hmac-sha2-256) binary packet protocol.
 *
 * Deliberately narrow in scope: a single key exchange (no re-keying - sessions used by this
 * application are short-lived exec/scp operations), a single key-exchange algorithm
 * (curve25519-sha256), and a single cipher/mac pair (aes256-ctr / hmac-sha2-256). This mirrors
 * what every server in practice supports, without the size and risk of a general-purpose
 * SSH client that negotiates the full algorithm matrix.
 *
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Transport
{
    private const string CLIENT_VERSION = 'SSH-2.0-Papdy_1.0';

    /**
     * @var array<int, string>
     */
    private const array KEX_ALGORITHMS = ['curve25519-sha256', 'curve25519-sha256@libssh.org'];

    /**
     * @var array<int, string>
     */
    private const array HOST_KEY_ALGORITHMS = ['rsa-sha2-256', 'ssh-ed25519', 'ssh-rsa'];

    private const string CIPHER_ALGORITHM = 'aes256-ctr';
    private const string MAC_ALGORITHM = 'hmac-sha2-256';

    private Socket $socket;
    private string $serverVersion;

    private int $sendSequence = 0;
    private int $receiveSequence = 0;

    private ?CtrCipher $sendCipher = null;
    private ?CtrCipher $receiveCipher = null;
    private ?string $sendMacKey = null;
    private ?string $receiveMacKey = null;

    private string $sessionId = '';

    /**
     * @throws RandomException
     * @throws \SodiumException
     */
    public function connect(string $host, int $port, float $timeout): void
    {
        $this->socket = Socket::connectTcp($host, $port, $timeout);

        $this->exchangeVersions();
        $this->performKeyExchange($host, $port);
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * @throws RandomException
     */
    public function sendPacket(string $payload): void
    {
        $blockSize = null !== $this->sendCipher ? 16 : 8;

        $paddingLength = $blockSize - ((5 + strlen($payload)) % $blockSize);

        if ($paddingLength < 4) {
            $paddingLength += $blockSize;
        }

        $packetLength = 1 + strlen($payload) + $paddingLength;
        $unencryptedPacket = pack('N', $packetLength).chr($paddingLength).$payload.random_bytes($paddingLength);

        if (null !== $this->sendCipher && null !== $this->sendMacKey) {
            $mac = hash_hmac('sha256', pack('N', $this->sendSequence).$unencryptedPacket, $this->sendMacKey, true);
            $this->socket->write($this->sendCipher->crypt($unencryptedPacket).$mac);
        } else {
            $this->socket->write($unencryptedPacket);
        }

        $this->sendSequence = ($this->sendSequence + 1) & 0xFFFFFFFF;
    }

    /**
     * Reads the next packet, transparently skipping SSH_MSG_IGNORE/DEBUG/UNIMPLEMENTED,
     * and raising a clear error if the server sends SSH_MSG_DISCONNECT.
     *
     * @throws RandomException
     */
    public function readPacket(): string
    {
        while (true) {
            $payload = $this->readRawPacket();
            $type = ord($payload[0]);

            if (MessageType::DISCONNECT === $type) {
                $reader = new Reader(substr($payload, 1));
                $reader->readUint32();

                throw new SshException(sprintf('Server closed the connection: %s', $reader->readString()));
            }

            if (in_array($type, [MessageType::IGNORE, MessageType::DEBUG, MessageType::UNIMPLEMENTED], true)) {
                continue;
            }

            if (MessageType::GLOBAL_REQUEST === $type) {
                $this->handleGlobalRequest($payload);

                continue;
            }

            return $payload;
        }
    }

    public function close(): void
    {
        $this->socket->close();
    }

    /**
     * The server may send global requests unsolicited (e.g. OpenSSH's "hostkeys-00@openssh.com"
     * host key rotation hint). None of these are supported, so any that want a reply are told so.
     *
     * @throws RandomException
     */
    private function handleGlobalRequest(string $payload): void
    {
        $reader = new Reader(substr($payload, 1));
        $reader->readString();
        $wantReply = $reader->readBoolean();

        if ($wantReply) {
            $this->sendPacket(chr(MessageType::REQUEST_FAILURE));
        }
    }

    private function exchangeVersions(): void
    {
        $this->socket->write(self::CLIENT_VERSION."\r\n");

        do {
            $line = rtrim($this->socket->readLine(), "\r\n");
        } while (!str_starts_with($line, 'SSH-'));

        if (!str_starts_with($line, 'SSH-2.0-')) {
            throw new SshException(sprintf('Unsupported SSH protocol version: %s', $line));
        }

        $this->serverVersion = $line;
    }

    /**
     * @throws RandomException
     * @throws \SodiumException
     */
    private function performKeyExchange(string $host, int $port): void
    {
        $cookie = random_bytes(16);

        $clientKexInit = (new Buffer())
            ->writeByte(MessageType::KEXINIT)
            ->writeRaw($cookie)
            ->writeNameList(self::KEX_ALGORITHMS)
            ->writeNameList(self::HOST_KEY_ALGORITHMS)
            ->writeNameList([self::CIPHER_ALGORITHM])
            ->writeNameList([self::CIPHER_ALGORITHM])
            ->writeNameList([self::MAC_ALGORITHM])
            ->writeNameList([self::MAC_ALGORITHM])
            ->writeNameList(['none'])
            ->writeNameList(['none'])
            ->writeNameList([])
            ->writeNameList([])
            ->writeBoolean(false)
            ->writeUint32(0)
            ->toString();

        $this->sendPacket($clientKexInit);
        $serverKexInit = $this->readPacket();

        if (MessageType::KEXINIT !== ord($serverKexInit[0])) {
            throw new SshException('Expected SSH_MSG_KEXINIT from server.');
        }

        $serverAlgorithms = $this->parseKexInit($serverKexInit);

        $this->negotiate(self::KEX_ALGORITHMS, $serverAlgorithms[0], 'key exchange');
        $this->negotiate(self::HOST_KEY_ALGORITHMS, $serverAlgorithms[1], 'server host key');
        $this->negotiate([self::CIPHER_ALGORITHM], $serverAlgorithms[2], 'client-to-server cipher');
        $this->negotiate([self::CIPHER_ALGORITHM], $serverAlgorithms[3], 'server-to-client cipher');
        $this->negotiate([self::MAC_ALGORITHM], $serverAlgorithms[4], 'client-to-server MAC');
        $this->negotiate([self::MAC_ALGORITHM], $serverAlgorithms[5], 'server-to-client MAC');
        $this->negotiate(['none'], $serverAlgorithms[6], 'client-to-server compression');
        $this->negotiate(['none'], $serverAlgorithms[7], 'server-to-client compression');

        $clientPrivateKey = random_bytes(32);
        $clientPublicKey = sodium_crypto_scalarmult_base($clientPrivateKey);

        $this->sendPacket(
            (new Buffer())->writeByte(MessageType::KEX_ECDH_INIT)->writeString($clientPublicKey)->toString()
        );

        $reply = $this->readPacket();

        if (MessageType::KEX_ECDH_REPLY !== ord($reply[0])) {
            throw new SshException('Expected SSH_MSG_KEX_ECDH_REPLY from server.');
        }

        $replyReader = new Reader(substr($reply, 1));
        $hostKeyBlob = $replyReader->readString();
        $serverPublicKey = $replyReader->readString();
        $signatureBlob = $replyReader->readString();

        $sharedSecret = sodium_crypto_scalarmult($clientPrivateKey, $serverPublicKey);

        $exchangeHash = hash(
            'sha256',
            (new Buffer())->writeString(self::CLIENT_VERSION)->writeString($this->serverVersion)->writeString(
                $clientKexInit
            )->writeString($serverKexInit)->writeString($hostKeyBlob)->writeString($clientPublicKey)->writeString(
                $serverPublicKey
            )->writeMpint($sharedSecret)->toString(),
            true
        );

        if (!HostKeyVerifier::verifySignature($hostKeyBlob, $exchangeHash, $signatureBlob)) {
            throw new SshException('Server host key signature verification failed.');
        }

        HostKeyVerifier::checkKnownHosts($host, $port, $hostKeyBlob);

        if ('' === $this->sessionId) {
            $this->sessionId = $exchangeHash;
        }

        $deriveKey = fn (string $letter, int $length): string => substr(
            hash(
                'sha256',
                (new Buffer())->writeMpint($sharedSecret)->writeRaw($exchangeHash)->writeRaw($letter)->writeRaw(
                    $this->sessionId
                )->toString(),
                true
            ),
            0,
            $length
        );

        $ivC2S = $deriveKey('A', 16);
        $ivS2C = $deriveKey('B', 16);
        $encKeyC2S = $deriveKey('C', 32);
        $encKeyS2C = $deriveKey('D', 32);
        $macKeyC2S = $deriveKey('E', 32);
        $macKeyS2C = $deriveKey('F', 32);

        $this->sendPacket(chr(MessageType::NEWKEYS));

        $serverNewKeys = $this->readPacket();

        if (MessageType::NEWKEYS !== ord($serverNewKeys[0])) {
            throw new SshException('Expected SSH_MSG_NEWKEYS from server.');
        }

        $this->sendCipher = new CtrCipher($encKeyC2S, $ivC2S);
        $this->receiveCipher = new CtrCipher($encKeyS2C, $ivS2C);
        $this->sendMacKey = $macKeyC2S;
        $this->receiveMacKey = $macKeyS2C;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseKexInit(string $payload): array
    {
        $reader = new Reader(substr($payload, 1));
        $reader->readRaw(16); // cookie

        $lists = [];

        for ($i = 0; $i < 10; ++$i) {
            $lists[] = $reader->readNameList();
        }

        return $lists;
    }

    /**
     * @param array<int, string> $clientPreference
     * @param array<int, string> $serverList
     */
    private function negotiate(array $clientPreference, array $serverList, string $label): void
    {
        foreach ($clientPreference as $algorithm) {
            if (in_array($algorithm, $serverList, true)) {
                return;
            }
        }

        throw new SshException(sprintf('Server does not support a compatible %s algorithm.', $label));
    }

    private function readRawPacket(): string
    {
        $blockSize = null !== $this->receiveCipher ? 16 : 8;

        if (null !== $this->receiveCipher && null !== $this->receiveMacKey) {
            $decryptedFirst = $this->receiveCipher->crypt($this->socket->read($blockSize));

            /** @var array<int, int> $unpacked */
            $unpacked = unpack('N', substr($decryptedFirst, 0, 4));
            $packetLength = $unpacked[1];

            $remainingLength = $packetLength + 4 - $blockSize;
            $decryptedRest = $remainingLength > 0
                ? $this->receiveCipher->crypt($this->socket->read($remainingLength))
                : '';

            $unencryptedPacket = $decryptedFirst.$decryptedRest;

            $mac = $this->socket->read(32);
            $expectedMac = hash_hmac(
                'sha256',
                pack('N', $this->receiveSequence).$unencryptedPacket,
                $this->receiveMacKey,
                true
            );

            if (!hash_equals($expectedMac, $mac)) {
                throw new SshException('SSH packet MAC verification failed.');
            }
        } else {
            $lengthBytes = $this->socket->read(4);

            /** @var array<int, int> $unpacked */
            $unpacked = unpack('N', $lengthBytes);
            $packetLength = $unpacked[1];

            $unencryptedPacket = $lengthBytes.$this->socket->read($packetLength);
        }

        $this->receiveSequence = ($this->receiveSequence + 1) & 0xFFFFFFFF;

        $paddingLength = ord($unencryptedPacket[4]);
        $payloadLength = $packetLength - 1 - $paddingLength;

        return substr($unencryptedPacket, 5, $payloadLength);
    }
}
