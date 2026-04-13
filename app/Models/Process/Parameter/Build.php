<?php

declare(strict_types=1);

namespace App\Models\Process\Parameter;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Build extends Base
{
    /**
     * @return array<int|string, string>
     */
    protected function getServerValues(): array
    {
        return [
            'phpExecutable',
            'composerExecutable',
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function getServerLists(): array
    {
        return [];
    }

    /**
     * @return array<int|string, string>
     */
    protected function getComponentValues(): array
    {
        return [
            'path' => 'buildPath',
            'user' => 'buildUser',
            'type' => 'buildType',
            'url' => 'buildUrl',
            'project' => 'buildProject',
            'projectUser' => 'buildProjectUser',
            'projectPassword' => 'buildProjectPassword',
            'sharedPath' => 'buildSharedPath',
            'memoryLimit' => 'buildMemoryLimit',
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function getComponentLists(): array
    {
        return ['link' => 'buildLink@', 'env' => 'buildEnv@'];
    }
}
