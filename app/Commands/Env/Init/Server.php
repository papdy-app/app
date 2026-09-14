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
        return 'Initialize a server';
    }

    protected function getCommandParameters(): array
    {
        return [
            $this->prepareInputOption('name', 'Name of server'),
            $this->prepareDefaultInputOption('type', 'local', 'Server type (local/remote/ssh)'),
            $this->prepareInputOption('host', 'Host if type != local'),
            $this->prepareInputOption('sshUser', 'User if type == ssh'),
            $this->prepareDefaultInputOption('sshPort', 22, 'Port if type == ssh'),
            $this->prepareDefaultInputOption('sshAuth', 'agent', 'Auth if type == ssh (agent|password|key|file)'),
            $this->prepareInputOption('sshPassword', 'Password if type == ssh and sshAuth == password'),
            $this->prepareInputOption('sshPrivateKey', 'Private key if type == ssh and sshAuth == keys'),
            $this->prepareInputOption('sshPrivateKeyFile', 'Private key if type == ssh and sshAuth == files'),
            $this->prepareDefaultInputOption('shell', 'bash', 'Shell to use'),
        ];
    }

    /**
     * @throws BindingResolutionException
     */
    protected function executeCommand(): int
    {
        $name = $this->getRequiredOption('name', 'No server name specified!');
        $type = $this->getAllowedOption('type', ['local', 'remote', 'ssh'], 'Invalid server type specified: %s');

        $host = 'remote' === $type || 'ssh' === $type ? $this->getRequiredOption('host', 'No host specified!') : null;

        $sshUser = null;
        $sshPort = null;
        $sshAuth = null;
        $sshPassword = null;
        $sshPrivateKey = null;
        $sshPrivateKeyFile = null;

        if ('ssh' === $type) {
            $sshUser = $this->getRequiredOption('sshUser', 'No SSH user specified!');
            $sshPort = $this->getRequiredOption('sshPort', 'No SSH port specified!');
            $sshAuth = $this->getRequiredOption('sshAuth', 'No SSH auth specified!');

            if (!in_array($sshAuth, ['agent', 'password', 'key', 'file'])) {
                return $this->exitWithError(sprintf('Invalid SSH auth specified: %s', $sshAuth));
            }

            if ('password' === $sshAuth) {
                $sshPassword = $this->getRequiredOption('sshPassword', 'No SSH password specified!');
            } elseif ('key' === $sshAuth) {
                $sshPrivateKey = $this->getRequiredOption('sshPrivateKey', 'No SSH private key specified!');
            } elseif ('file' === $sshAuth) {
                $sshPrivateKeyFile = $this->getRequiredOption(
                    'sshPrivateKeyFile',
                    'No SSH private key file specified!'
                );
            }
        }

        $shell = $this->getRequiredOption('shell', 'No shell specified!');

        $process = $this->app->make(\App\Models\Process\Env\Init\Server::class);

        $process->execute(
            $name,
            $type,
            $host,
            $sshPort,
            $sshUser,
            $sshAuth,
            $sshPassword,
            $sshPrivateKey,
            $sshPrivateKeyFile,
            $shell,
        );

        return self::SUCCESS;
    }
}
