<?php

declare(strict_types=1);

namespace App\Models\Process\Env\Init\Build;

use App\Models\Process\Base;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Env extends Base
{
    public function execute(
        ?string $serverName,
        ?string $host,
        ?string $id,
        string $name,
        string $value
    ): void {
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

        $this->config->add(
            $id,
            'env',
            sprintf(
                '%s=%s',
                $name,
                $value
            )
        );
    }
}
