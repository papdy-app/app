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
    public function execute(OutputInterface $output, string $name): void
    {
        $this->run(
            $output,
            'build/build.sh',
            ['build:all'],
            ['name' => $name]
        );
    }
}
