<?php

declare(strict_types=1);

namespace App\Commands\Env\Init;

use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Deploy extends Deploy\Base
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
        return array_merge(
            $this->getBaseCommandParameters(),
            [
                $this->prepareInputOption('path', 'Path to deploy directory'),
                $this->prepareInputOption('user', 'User to use for deploy'),
                $this->prepareInputOption('sharedPath', 'Path to shared directory'),
                $this->prepareInputOption('webPath', 'Path of web server'),
            ]
        );
    }

    /**
     * @throws BindingResolutionException
     */
    protected function executeCommand(): int
    {
        [$serverName, $host, $id] = $this->getBaseOptions();

        $path = $this->getRequiredOption('path', 'No deploy path specified!');
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
