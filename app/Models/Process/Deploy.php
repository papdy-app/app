<?php

declare(strict_types=1);

namespace App\Models\Process;

use App\Models\Command\Local;
use App\Models\Command\Remote;
use App\Models\Command\SSH;
use App\Models\Config;
use App\Models\Path;
use FeWeDev\Base\Arrays;
use FeWeDev\Base\Files;
use FeWeDev\Base\Strings;
use FeWeDev\Base\Variables;
use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Deploy extends Base
{
    public function __construct(
        Variables $variables,
        Arrays $arrays,
        Strings $strings,
        Config $config,
        Application $app,
        Path $path,
        Local $local,
        Remote $remote,
        SSH $ssh,
        protected Files $files,
    ) {
        parent::__construct($variables, $arrays, $strings, $config, $app, $path, $local, $remote, $ssh);
    }

    /**
     * @param array<int, string> $servers
     */
    public function execute(OutputInterface $output, array $servers, string $name, ?string $localBuildNameFile): void
    {
        $buildServerBuildNameFile = $this->runScript(
            $output,
            'deploy/build.sh',
            [],
            ['build:single'],
            ['name' => $name]
        );

        $localBuildPath = storage_path('build');

        if (str_contains($localBuildPath, 'phar://')) {
            $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);

            $localBuildPath = implode(DIRECTORY_SEPARATOR, [
                $tempDir,
                'papdy',
                app()->version(),
                'storage',
                'build',
            ]);
        }

        if (!file_exists($localBuildPath)) {
            $this->files->createDirectory($localBuildPath, 0755);
        }

        if ($this->variables->isEmpty($localBuildNameFile)) {
            $localBuildNameFile = sprintf('%s/%s', $localBuildPath, basename($buildServerBuildNameFile));

            $this->download($output, $buildServerBuildNameFile, $localBuildNameFile, ['build:single']);
        }

        $deployServerBuildNameFile = sprintf(
            '%s%s%s',
            sys_get_temp_dir(),
            DIRECTORY_SEPARATOR,
            basename($buildServerBuildNameFile),
        );

        $deployServer = sprintf('deploy:%s', count($servers) > 0 ? implode(',', $servers) : 'all');

        $this->upload($output, $localBuildNameFile, $deployServerBuildNameFile, [$deployServer]);

        $deployId = date('Y_m_d_H_m_s');

        $this->runScript(
            $output,
            'deploy/deploy.sh',
            [],
            [$deployServer],
            ['name' => $name, 'deployId' => $deployId, 'buildNameFile' => $deployServerBuildNameFile],
            [],
            [],
            ['deployPre'],
            ['deployPost'],
        );

        $this->delete($output, $deployServerBuildNameFile, [$deployServer]);

        $output->writeln(sprintf('Deleting file at: %s', $localBuildNameFile));

        unlink($localBuildNameFile);
    }
}
