<?php

declare(strict_types=1);

namespace App\Models\Process\Env\Init;

use App\Exceptions\InputOptionException;
use App\Models\Process\Base;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Build extends Base
{
    public function execute(
        ?string $serverName,
        ?string $host,
        ?string $id,
        string $path,
        ?string $user,
        string $type,
        string $url,
        ?string $project,
        ?string $projectUser,
        ?string $projectPassword,
        ?string $sharedPath,
        ?string $phpExecutable,
        ?string $composerExecutable,
        ?string $memoryLimit,
    ): void {
        if ($type !== 'git' && $type !== 'composer') {
            throw new InputOptionException(
                sprintf(
                    'Invalid build type: %s',
                    $type
                )
            );
        }

        $serverName = $this->getServerName(
            $serverName,
            $host
        );

        if ($this->variables->isEmpty($id)) {
            $id = sprintf(
                '%s_build',
                $serverName
            );
        }

        $this->config->set(
            $serverName,
            'build',
            $id
        );

        $this->config->set(
            $id,
            'path',
            $path
        );

        if (!$this->variables->isEmpty($user)) {
            $this->config->set(
                $id,
                'user',
                $user
            );
        }

        $this->config->set(
            $id,
            'type',
            $type
        );

        $this->config->set(
            $id,
            'url',
            $url
        );

        if (!$this->variables->isEmpty($project)) {
            $this->config->set(
                $id,
                'project',
                $project
            );
        }

        if (!$this->variables->isEmpty($projectUser)) {
            $this->config->set(
                $id,
                'projectUser',
                $projectUser
            );
        }

        if (!$this->variables->isEmpty($projectPassword)) {
            $this->config->set(
                $id,
                'projectPassword',
                $projectPassword
            );
        }

        if (!$this->variables->isEmpty($sharedPath)) {
            $this->config->set(
                $id,
                'sharedPath',
                $sharedPath
            );
        }

        if (!$this->variables->isEmpty($phpExecutable)) {
            $this->config->set(
                $serverName,
                'phpExecutable',
                $phpExecutable
            );
        }

        if (!$this->variables->isEmpty($composerExecutable)) {
            $this->config->set(
                $serverName,
                'composerExecutable',
                $composerExecutable
            );
        }

        if (!$this->variables->isEmpty($memoryLimit)) {
            $this->config->set(
                $id,
                'memoryLimit',
                $memoryLimit
            );
        }
    }
}
