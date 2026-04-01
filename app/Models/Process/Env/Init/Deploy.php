<?php

declare(strict_types=1);

namespace App\Models\Process\Env\Init;

use App\Models\Process\Base;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Deploy extends Base
{
    public function execute(
        ?string $serverName,
        ?string $host,
        ?string $id,
        string $path,
        ?string $user,
        ?string $sharedPath,
        ?string $webPath,
    ): void {
        $serverName = $this->getServerName(
            $serverName,
            $host
        );

        if ($this->variables->isEmpty($id)) {
            $id = sprintf(
                '%s_deploy',
                $serverName
            );
        }

        $this->config->set(
            $serverName,
            'deploy',
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

        if (!$this->variables->isEmpty($sharedPath)) {
            $this->config->set(
                $id,
                'sharedPath',
                $sharedPath
            );
        }

        if (!$this->variables->isEmpty($webPath)) {
            $this->config->set(
                $id,
                'webPath',
                $webPath
            );
        }
    }
}
