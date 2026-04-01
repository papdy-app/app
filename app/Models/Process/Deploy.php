<?php

declare(strict_types=1);

namespace App\Models\Process;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Deploy extends Base
{
    public function execute(OutputInterface $output, string $name): void
    {
        $buildServerBuildNameFile = $this->run(
            $output,
            'deploy/build.sh',
            ['build'],
            ['name' => $name]
        );

        $localBuildPath = storage_path('build');
        $localBuildNameFile = sprintf(
            '%s/%s',
            $localBuildPath,
            basename($buildServerBuildNameFile),
        );

        $this->download(
            $output,
            $buildServerBuildNameFile,
            $localBuildNameFile,
            ['build'],
        );

        $deployServerBuildNameFile = sprintf(
            '/tmp/%s',
            basename($buildServerBuildNameFile),
        );

        $this->upload(
            $output,
            $localBuildNameFile,
            $deployServerBuildNameFile,
            ['deploy:all'],
        );

        $deployId = date('Y_m_d_H_m_s');

        $this->run(
            $output,
            'deploy/deploy.sh',
            ['deploy:all'],
            ['name' => $name, 'deployId' => $deployId, 'buildNameFile' => $deployServerBuildNameFile],
        );
    }
}
