<?php

declare(strict_types=1);

namespace App\Commands\Env\Init;

use App\Commands\Base;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Build extends Base
{
    protected function getCommandName(): string
    {
        return 'env:init:build';
    }

    protected function getCommandDescription(): string
    {
        return 'Initialize the build environment';
    }

    protected function getCommandParameters(): array
    {
        return [
            '--serverName= : Name of server to build on',
            '--host=localhost : Host of server to build on',
            '--id= : Id of build, default: [serverName]_build',
            '--path= : Path to build directory',
            '--user= : User to use for build',
            '--type=git : Type of build (git or composer)',
            '--url= : URL to use',
            '--project= : Project to use if composer build',
            '--projectUser= : Project user to use if composer build',
            '--projectPassword= : Project password to use if composer build',
            '--sharedPath= : Path to shared directory',
            '--phpExecutable= : Path to PHP executable',
            '--composerExecutable= : Path to Composer executable',
            '--memoryLimit= : Use this memory limit',
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
        $path = $this->getRequiredOption(
            'path',
            'No build path specified!'
        );
        $user = $this->getOption('user');
        $type = $this->getRequiredOption(
            'type',
            'No build type specified!'
        );
        $url = $this->getRequiredOption(
            'url',
            'No build URL specified!'
        );
        $project = $this->getOption('project');
        $projectUser = $this->getOption('projectUser');
        $projectPassword = $this->getOption('projectPassword');
        $sharedPath = $this->getOption('sharedPath');
        $phpExecutable = $this->getOption('phpExecutable');
        $composerExecutable = $this->getOption('composerExecutable');
        $memoryLimit = $this->getOption('memoryLimit');

        $process = $this->app->make(\App\Models\Process\Env\Init\Build::class);

        $process->execute(
            $serverName,
            $host,
            $id,
            $path,
            $user,
            $type,
            $url,
            $project,
            $projectUser,
            $projectPassword,
            $sharedPath,
            $phpExecutable,
            $composerExecutable,
            $memoryLimit,
        );

        return self::SUCCESS;
    }
}
