<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Update extends Base
{
    protected function getCommandName(): string
    {
        return 'update';
    }

    protected function getCommandDescription(): string
    {
        return 'Update Papdy to the latest release';
    }

    protected function getCommandParameters(): array
    {
        return [$this->prepareInputFlag('check', 'Only check whether a newer version is available')];
    }

    /**
     * @throws BindingResolutionException
     */
    protected function executeCommand(): int
    {
        $process = $this->app->make(\App\Models\Process\Update::class);

        $process->execute($this->getOutput(), (string) $this->getApplication()?->getVersion(), $this->getFlag('check'));

        return self::SUCCESS;
    }
}
