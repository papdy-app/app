<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Config;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Server
{
    public function __construct(protected Config $config) {}

    public function getHost(string $serverName): string
    {
        return $this->config->requiredValue($serverName, 'host');
    }

    public function getPort(string $serverName): int
    {
        return intval($this->config->requiredValue($serverName, 'port'));
    }

    public function getUser(string $serverName): string
    {
        return $this->config->requiredValue($serverName, 'user');
    }

    public function getShell(string $serverName): string
    {
        return $this->config->requiredValue($serverName, 'shell');
    }

    public function getAuth(string $serverName): string
    {
        return $this->config->requiredValue($serverName, 'auth');
    }

    public function getPassword(string $serverName): string
    {
        return $this->config->requiredValue($serverName, 'password');
    }

    public function getPrivateKey(string $serverName): string
    {
        return $this->config->requiredValue($serverName, 'privateKey');
    }

    public function getPrivateKeyFile(string $serverName): string
    {
        return $this->config->requiredValue($serverName, 'privateKeyFile');
    }
}
