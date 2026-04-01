<?php

declare(strict_types=1);

namespace App\Commands\Env\Init\Build;

use App\Commands\Base;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Env extends Base
{
    protected function getCommandName(): string
    {
        return 'env:init:build:env';
    }

    protected function getCommandDescription(): string
    {
        return 'Add environment variable in build process';
    }

    protected function getCommandParameters(): array
    {
        return [
            '--serverName= : Name of server to build on',
            '--host=localhost : Host of server to build on',
            '--id= : Id of build, default: [serverName]_build',
            '--name= : Name of environment variable',
            '--value= : Value of environment variable',
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
        $name = $this->getRequiredOption(
            'name',
            'No name specified!'
        );
        $value = $this->getRequiredOption(
            'value',
            'No value specified!'
        );

        $process = $this->app->make(\App\Models\Process\Env\Init\Build\Env::class);

        $process->execute(
            $serverName,
            $host,
            $id,
            $name,
            $value
        );

        return self::SUCCESS;
    }
}
