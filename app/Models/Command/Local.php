<?php

declare(strict_types=1);

namespace App\Models\Command;

use App\Exceptions\ScriptException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Local extends Base
{
    public function run(
        OutputInterface $output,
        string $serverName,
        string $scriptPath,
        array $parameters,
        bool $isQuiet
    ): string {
        $command = $this->completeCommand(
            $scriptPath,
            $parameters
        );

        if (!$isQuiet) {
            $output->writeln($command);
        }

        [$exitCode, $scriptOutput] = $this->process(
            $command,
            $isQuiet
        );

        if (0 !== $exitCode) {
            throw new ScriptException(
                sprintf(
                    'Error while executing script: %s',
                    $scriptPath
                )
            );
        }

        return $scriptOutput;
    }

    /**
     * @return array<int, null|int|string>
     */
    private function process(string $command, bool $isQuiet): array
    {
        $proc = popen(
            "{$command} 2>&1 ; echo Exit status: $?",
            'r'
        );

        if (false === $proc) {
            throw new ScriptException(
                sprintf(
                    'Error while executing command: %s',
                    $command
                )
            );
        }

        $completeOutput = '';

        while (!feof($proc)) {
            $liveOutput = fread(
                $proc,
                4096
            );
            $completeOutput = $completeOutput.$liveOutput;
            if (!$isQuiet) {
                echo "{$liveOutput}";
            }
            @flush();
        }

        pclose($proc);

        return $this->processResult($completeOutput);
    }

    public function download(
        OutputInterface $output,
        string $serverName,
        string $serverFileName,
        string $localFileName,
        bool $isQuiet
    ): void {
        if (!file_exists($serverFileName)) {
            throw new ScriptException(
                sprintf(
                    'Server file not found: %s',
                    $serverFileName
                )
            );
        }

        if (!file_exists(dirname($localFileName))) {
            if (!$isQuiet) {
                $output->writeln(
                    sprintf(
                        'Creating directory: %s',
                        dirname($localFileName),
                    )
                );
            }

            mkdir(
                dirname($localFileName),
                0755,
                true
            );
        }

        if (!$isQuiet) {
            $output->writeln(
                sprintf(
                    'Copying file from: %s to: %s',
                    $serverFileName,
                    $localFileName
                )
            );
        }

        copy(
            $serverFileName,
            $localFileName
        );
    }

    public function upload(
        OutputInterface $output,
        string $serverName,
        string $localFileName,
        string $serverFileName,
        bool $isQuiet
    ): void {
        if (!file_exists($localFileName)) {
            throw new ScriptException(
                sprintf(
                    'Local file not found: %s',
                    $localFileName
                )
            );
        }

        if (!file_exists(dirname($serverFileName))) {
            if (!$isQuiet) {
                $output->writeln(
                    sprintf(
                        'Creating directory: %s',
                        dirname($serverFileName),
                    )
                );
            }

            mkdir(
                dirname($serverFileName),
                0755,
                true
            );
        }

        if (!$isQuiet) {
            $output->writeln(
                sprintf(
                    'Copying file from: %s to: %s',
                    $localFileName,
                    $serverFileName,
                )
            );
        }

        copy(
            $localFileName,
            $serverFileName,
        );
    }
}
