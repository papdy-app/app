<?php

declare(strict_types=1);

namespace App\Models\Process\Parameter;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Deploy extends Base
{
    protected function getServerValues(): array
    {
        return [];
    }

    protected function getServerLists(): array
    {
        return [];
    }

    protected function getComponentValues(): array
    {
        return [
            'path' => 'deployPath',
            'webPath',
            'user' => 'deployUser',
            'sharedPath' => 'deploySharedPath',
        ];
    }

    protected function getComponentLists(): array
    {
        return ['link' => 'deployLink@'];
    }
}
