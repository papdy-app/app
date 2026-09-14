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

    public function execute(OutputInterface $output, string $name): void
    {
        $buildServerBuildNameFile = $this->runScript($output, 'deploy/build.sh', [], ['build'], ['name' => $name]);

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

        $localBuildNameFile = sprintf('%s/%s', $localBuildPath, basename($buildServerBuildNameFile));

        $this->download($output, $buildServerBuildNameFile, $localBuildNameFile, ['build']);

        $deployServerBuildNameFile = sprintf(
            '%s%s%s',
            sys_get_temp_dir(),
            DIRECTORY_SEPARATOR,
            basename($buildServerBuildNameFile),
        );

        $this->upload($output, $localBuildNameFile, $deployServerBuildNameFile, ['deploy:all']);

        $deployId = date('Y_m_d_H_m_s');

        $this->runScript(
            $output,
            'deploy/deploy.sh',
            [],
            ['deploy:all'],
            ['name' => $name, 'deployId' => $deployId, 'buildNameFile' => $deployServerBuildNameFile],
            [],
            [],
            ['deployPre'],
            ['deployPost'],
        );
    }
}
