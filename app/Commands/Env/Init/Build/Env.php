<?php

declare(strict_types=1);

namespace App\Commands\Env\Init\Build;

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
        return array_merge(
            $this->getBaseCommandParameters(),
            [
                $this->prepareInputOption('name', 'Name of environment variable'),
                $this->prepareInputOption('value', 'Value of environment variable'),
            ]
        );
    }

    /**
     * @throws BindingResolutionException
     */
    protected function executeCommand(): int
    {
        [$serverName, $host, $id] = $this->getBaseOptions();

        $name = $this->getRequiredOption('name', 'No name specified!');
        $value = $this->getRequiredOption('value', 'No value specified!');

        $process = $this->app->make(\App\Models\Process\Env\Init\Build\Env::class);

        $process->execute($serverName, $host, $id, $name, $value);

        return self::SUCCESS;
    }
}
