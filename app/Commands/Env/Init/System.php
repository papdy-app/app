<?php

declare(strict_types=1);

namespace App\Commands\Env\Init;

use App\Commands\Base;
use App\Exceptions\InputOptionException;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class System extends Base
{
    protected function getCommandName(): string
    {
        return 'env:init:system';
    }

    protected function getCommandDescription(): string
    {
        return 'Init a new system';
    }

    protected function getCommandParameters(): array
    {
        return [
            '--name=default : Name of system, default: system',
        ];
    }

    /**
     * @throws BindingResolutionException
     */
    protected function executeCommand(): int
    {
        $name = $this->option('name');

        if (is_array($name)) {
            throw new InputOptionException('The option name in invalid.');
        }

        $process = $this->app->make(\App\Models\Process\Env\Init\System::class);

        $process->execute(strval($name));

        return self::SUCCESS;
    }
}
