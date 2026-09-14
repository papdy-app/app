<?php

declare(strict_types=1);

namespace App\Models\Process\Env\Init\Deploy;

use App\Models\Process\Base;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Pre extends Base
{
    public function execute(?string $serverName, ?string $host, ?string $id, string $command): void
    {
        $serverName = $this->getServerName($serverName, $host);

        if ($this->variables->isEmpty($id)) {
            $id = sprintf('%s_deploy', $serverName);
        }

        $this->config->add($id, 'pre', $command);
    }
}
