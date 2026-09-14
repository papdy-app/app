<?php

declare(strict_types=1);

namespace App\Commands\Env\Init\Deploy;

use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Pre extends Base
{
    protected function getCommandName(): string
    {
        return 'env:init:deploy:pre';
    }

    protected function getCommandDescription(): string
    {
        return 'Add a pre deployment command';
    }

    protected function getCommandParameters(): array
    {
        return array_merge(
            $this->getBaseCommandParameters(),
            [
                $this->prepareInputOption('command', 'Command to execute'),
            ]
        );
    }

    /**
     * @throws BindingResolutionException
     */
    protected function executeCommand(): int
    {
        [$serverName, $host, $id] = $this->getBaseOptions();

        $command = $this->getRequiredOption('command', 'No command specified!');

        $process = $this->app->make(\App\Models\Process\Env\Init\Deploy\Pre::class);

        $process->execute($serverName, $host, $id, $command);

        return self::SUCCESS;
    }
}
