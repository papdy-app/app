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
class Link extends Base
{
    protected function getCommandName(): string
    {
        return 'env:init:build:link';
    }

    protected function getCommandDescription(): string
    {
        return 'Add link in build process';
    }

    protected function getCommandParameters(): array
    {
        return [
            '--serverName= : Name of server to build on',
            '--host=localhost : Host of server to build on',
            '--id= : Id of build, default: [serverName]_build',
            '--source= : Source path of link',
            '--target= : Target path of link',
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
        $source = $this->getRequiredOption(
            'source',
            'No source specified!'
        );
        $target = $this->getOption('target');

        $process = $this->app->make(\App\Models\Process\Env\Init\Build\Link::class);

        $process->execute(
            $serverName,
            $host,
            $id,
            $source,
            $target
        );

        return self::SUCCESS;
    }
}
