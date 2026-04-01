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
class Deploy extends Base
{
    protected function getCommandName(): string
    {
        return 'env:init:deploy';
    }

    protected function getCommandDescription(): string
    {
        return 'Initialize the deploy environment';
    }

    protected function getCommandParameters(): array
    {
        return [
            '--serverName= : Name of server to deploy on',
            '--host=localhost : Host of server to deploy on',
            '--id= : Id of build, default: [serverName]_deploy',
            '--path= : Path to deploy directory',
            '--user= : User to use for deploy',
            '--sharedPath= : Path to shared directory',
            '--webPath= : Path of web server',
        ];
    }

    /**
     * @throws BindingResolutionException
     */
    protected function executeCommand(): int
    {
        $serverName = $this->getOption('serverName');
        $host = $this->getOption('host');
        $id = $this->getOption('id');
        $path = $this->getRequiredOption(
            'path',
            'No deploy path specified!'
        );
        $user = $this->getOption('user');
        $sharedPath = $this->getOption('sharedPath');
        $webPath = $this->getOption('webPath');

        $process = $this->app->make(\App\Models\Process\Env\Init\Deploy::class);

        $process->execute(
            $serverName,
            $host,
            $id,
            $path,
            $user,
            $sharedPath,
            $webPath,
        );

        return self::SUCCESS;
    }
}
