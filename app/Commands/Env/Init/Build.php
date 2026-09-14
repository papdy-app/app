<?php

declare(strict_types=1);

namespace App\Commands\Env\Init;

use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Build extends Build\Base
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
        return array_merge(
            $this->getBaseCommandParameters(),
            [
                $this->prepareInputOption('path', 'Path to build directory'),
                $this->prepareInputOption('user', 'User to use for build'),
                $this->prepareDefaultInputOption('type', 'git', 'Type of build (git or composer)'),
                $this->prepareInputOption('url', 'URL to use'),
                $this->prepareInputOption('project', 'Project to use if composer build'),
                $this->prepareInputOption('projectUser', 'Project user to use if composer build'),
                $this->prepareInputOption('projectPassword', 'Project password to use if composer build'),
                $this->prepareInputOption('sharedPath', 'Path to shared directory'),
                $this->prepareInputOption('phpExecutable', 'Path to PHP executable'),
                $this->prepareInputOption('composerExecutable', 'Path to Composer executable'),
                $this->prepareInputOption('memoryLimit', 'Use this memory limit'),
            ]
        );
    }

    /**
     * @throws BindingResolutionException
     */
    protected function executeCommand(): int
    {
        [$serverName, $host, $id] = $this->getBaseOptions();

        $path = $this->getRequiredOption('path', 'No build path specified!');
        $user = $this->getOption('user');
        $type = $this->getAllowedOption('type', ['git', 'composer'], 'Invalid build type!');
        $url = $this->getRequiredOption('url', 'No build URL specified!');
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
