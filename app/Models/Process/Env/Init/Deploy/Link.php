<?php

declare(strict_types=1);

namespace App\Models\Process\Env\Init\Deploy;

use App\Models\Process\Base;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Link extends Base
{
    public function execute(
        ?string $serverName,
        ?string $host,
        ?string $id,
        string $source,
        ?string $target
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

        if ($this->variables->isEmpty($target)) {
            $target = $source;
        }

        $this->config->add(
            $id,
            'link',
            sprintf(
                '%s:%s',
                $source,
                $target
            )
        );
    }
}
