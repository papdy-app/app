<?php

declare(strict_types=1);

namespace App\Models\Process\Env\Init;

use App\Models\Process\Base;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Server extends Base
{
    public function execute(
        string $name,
        string $type,
        ?string $host,
        ?string $sshPort,
        ?string $sshUser,
        ?string $sshAuth,
        ?string $sshPassword,
        ?string $sshPrivateKey,
        ?string $sshPrivateKeyFile,
    ): void {
        $this->config->add(
            'system',
            'server',
            $name
        );
        $this->config->set(
            $name,
            'type',
            $type
        );

        if ('remote' === $type || 'ssh' === $type) {
            $this->config->set(
                $name,
                'host',
                $host
            );
        }

        if ('ssh' === $type) {
            $this->config->set(
                $name,
                'port',
                $sshPort
            );

            $this->config->set(
                $name,
                'user',
                $sshUser
            );

            $this->config->set(
                $name,
                'auth',
                $sshAuth
            );

            if ($sshAuth === 'password') {
                $this->config->set(
                    $name,
                    'password',
                    $sshPassword
                );
            } elseif ($sshAuth === 'key') {
                $this->config->set(
                    $name,
                    'privateKey',
                    $sshPrivateKey
                );
            } elseif ($sshAuth === 'file') {
                $this->config->set(
                    $name,
                    'privateKeyFile',
                    $sshPrivateKeyFile
                );
            }
        }
    }
}
