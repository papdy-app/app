<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 *
 * @internal
 *
 * @coversNothing
 */
class Deploy extends Base
{
    protected function getCommandName(): string
    {
        return 'deploy';
    }

    protected function getCommandDescription(): string
    {
        return 'Deploy the project';
    }

    protected function getCommandParameters(): array
    {
        return ['--name= : Name of the branch, tag or pull request to deploy'];
    }

    /**
     * @throws BindingResolutionException
     */
    protected function executeCommand(): int
    {
        $name = $this->getRequiredOption(
            'name',
            'No name to deploy specified!'
        );

        $process = $this->app->make(\App\Models\Process\Deploy::class);

        $process->execute(
            $this->getOutput(),
            $name
        );

        return self::SUCCESS;
    }
}
