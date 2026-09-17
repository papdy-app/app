<?php

declare(strict_types=1);

namespace App\Models\Process;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Build extends Base
{
    /**
     * @param array<int, string> $servers
     */
    public function execute(OutputInterface $output, array $servers, string $name): void
    {
        $buildServer = sprintf('build:%s', count($servers) > 0 ? implode(',', $servers) : 'all');

        $this->runScript(
            $output,
            'build/build.sh',
            [],
            [$buildServer],
            ['name' => $name],
            [],
            [],
            ['buildPre'],
            ['buildPost']
        );
    }
}
