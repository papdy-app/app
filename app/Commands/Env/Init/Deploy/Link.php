<?php

declare(strict_types=1);

namespace App\Commands\Env\Init\Deploy;

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
        return 'env:init:deploy:link';
    }

    protected function getCommandDescription(): string
    {
        return 'Add link in deploy process';
    }

    protected function getCommandParameters(): array
    {
        return [
            '--serverName= : Name of server to deploy on',
            '--host=localhost : Host of server to deploy on',
            '--id= : Id of deploy, default: [serverName]_deploy',
            '--source= : Source path of link',
            '--target= : Target path of link, default: [source]',
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

        $process = $this->app->make(\App\Models\Process\Env\Init\Deploy\Link::class);

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
