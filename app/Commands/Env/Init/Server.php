<?php

declare(strict_types=1);

namespace App\Commands\Env\Init;

use App\Commands\Base;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Server extends Base
{
    protected function getCommandName(): string
    {
        return 'env:init:server';
    }

    protected function getCommandDescription(): string
    {
        return 'Init a new server';
    }

    protected function getCommandParameters(): array
    {
        return [
            '--name= : Name of system',
            '--type=local : Server type (local/remote/ssh)',
            '--host= : Host if type != local',
            '--sshUser= : User if type == ssh',
            '--sshPort=22 : Port if type == ssh',
            '--sshAuth=agent : Auth if type == ssh (agent|password|key|file)',
            '--sshPassword= : Password if type == ssh and sshAuth == password',
            '--sshPrivateKey= : Private key if type == ssh and sshAuth == keys',
            '--sshPrivateKeyFile= : Private key if type == ssh and sshAuth == files',
        ];
    }

    /**
     * @throws BindingResolutionException
     */
    protected function executeCommand(): int
    {
        $name = $this->getRequiredOption(
            'name',
            'No server name specified!'
        );
        $type = $this->getRequiredOption(
            'type',
            'No server type specified!'
        );

        if ('local' !== $type && 'remote' !== $type && 'ssh' !== $type) {
            return $this->exitWithError(
                sprintf(
                    'Invalid server type specified: %s',
                    $type
                )
            );
        }

        if ('remote' === $type || 'ssh' === $type) {
            $host = $this->getRequiredOption(
                'host',
                'No host specified!'
            );
        } else {
            $host = null;
        }

        $sshUser = null;
        $sshPort = null;
        $sshAuth = null;
        $sshPassword = null;
        $sshPrivateKey = null;
        $sshPrivateKeyFile = null;

        if ('ssh' === $type) {
            $sshUser = $this->getRequiredOption(
                'sshUser',
                'No SSH user specified!'
            );
            $sshPort = $this->getRequiredOption(
                'sshPort',
                'No SSH port specified!'
            );
            $sshAuth = $this->getRequiredOption(
                'sshAuth',
                'No SSH auth specified!'
            );

            if (!in_array(
                $sshAuth,
                ['agent', 'password', 'key', 'file']
            )) {
                return $this->exitWithError(
                    sprintf(
                        'Invalid SSH auth specified: %s',
                        $sshAuth
                    )
                );
            }

            if ('password' === $sshAuth) {
                $sshPassword = $this->getRequiredOption(
                    'sshPassword',
                    'No SSH password specified!'
                );
            } elseif ('key' === $sshAuth) {
                $sshPrivateKey = $this->getRequiredOption(
                    'sshPrivateKey',
                    'No SSH private key specified!'
                );
            } elseif ('file' === $sshAuth) {
                $sshPrivateKeyFile = $this->getRequiredOption(
                    'sshPrivateKeyFile',
                    'No SSH private key file specified!'
                );
            }
        }

        $process = $this->app->make(\App\Models\Process\Env\Init\Server::class);

        $process->execute(
            $name,
            $type,
            $host,
            $sshUser,
            $sshPort,
            $sshAuth,
            $sshPassword,
            $sshPrivateKey,
            $sshPrivateKeyFile,
        );

        return self::SUCCESS;
    }
}
