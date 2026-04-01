<?php

declare(strict_types=1);

namespace App\Models\Process\Env\Init;

use App\Models\Process\Base;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class System extends Base
{
    public function execute(string $name): void
    {
        $this->config->set(
            'system',
            'name',
            $name
        );
    }
}
