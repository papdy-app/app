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
class Build extends Base
{
    protected function getCommandName(): string
    {
        return 'build';
    }

    protected function getCommandDescription(): string
    {
        return 'Build the project';
    }

    protected function getCommandParameters(): array
    {
        return ['--name= : Name of the branch, tag or pull request to build'];
    }

    /**
     * @throws BindingResolutionException
     */
    protected function executeCommand(): int
    {
        $name = $this->getRequiredOption(
            'name',
            'No name to build specified!'
        );

        $process = $this->app->make(\App\Models\Process\Build::class);

        $process->execute(
            $this->getOutput(),
            $name
        );

        return self::SUCCESS;
    }
}
