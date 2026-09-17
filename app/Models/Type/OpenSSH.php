<?php

declare(strict_types=1);

namespace App\Models\Type;

use App\Exceptions\ScriptException;
use App\Services\Command;
use App\Services\Path;
use App\Services\Server;
use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class OpenSSH implements Type
{
    public function __construct(
        protected Variables $variables,
        protected Arrays $arrays,
        protected Server $server,
        protected Path $path,
        protected Command $command
    ) {}

    public function run(
        OutputInterface $output,
        string $serverName,
        string $command,
        array $parameters,
        array $fileUploadParameters,
        array $fileDownloadParameters,
        bool $isQuiet
    ): string {
        $shell = $this->server->getShell($serverName);

        $parameterScriptPath = sprintf(
            '%s%s%s%s%s%s%s',
            $this->path->getBasePath(),
            DIRECTORY_SEPARATOR,
            'scripts',
            DIRECTORY_SEPARATOR,
            $shell,
            DIRECTORY_SEPARATOR,
            'prepare-parameters.sh'
        );

        $parameterScriptRemotePath = sprintf(
            '%s%s%s%s',
            DIRECTORY_SEPARATOR,
            'tmp',
            DIRECTORY_SEPARATOR,
            'prepare-parameters.sh'
        );

        $host = $this->server->getHost($serverName);
        $port = $this->server->getPort($serverName);
        $user = $this->server->getUser($serverName);

        $this->copyFileToHost(
            $output,
            $host,
            $port,
            $user,
            $parameterScriptPath,
            $parameterScriptRemotePath,
            $isQuiet,
            true
        );

        $this->copyFileToHost(
            $output,
            $host,
            $port,
            $user,
            $command,
            basename($command),
            $isQuiet,
            true
        );

        foreach ($fileUploadParameters as $fileParameter) {
            $file = $this->arrays->getValue($parameters, $fileParameter);

            if (!$this->variables->isEmpty($file)) {
                $file = $this->variables->stringValue($file);

                if (file_exists($file)) {
                    $this->copyFileToHost(
                        $output,
                        $host,
                        $port,
                        $user,
                        $file,
                        basename($file),
                        $isQuiet,
                    );

                    $parameters[$fileParameter] = basename($file);
                }
            }
        }

        $fileDownloads = [];

        foreach ($fileDownloadParameters as $fileParameter) {
            $file = $this->arrays->getValue($parameters, $fileParameter);

            if (!$this->variables->isEmpty($file)) {
                $file = $this->variables->stringValue($file);

                $parameters[$fileParameter] = basename($file);

                $fileDownloads[basename($file)] = $file;
            }
        }

        $command = $this->command->completeCommand(sprintf('./%s', basename($command)), $parameters);

        if (!$isQuiet) {
            $output->writeln($command);
        }

        $sshCommand = sprintf(
            'ssh -o "StrictHostKeyChecking accept-new" -o LogLevel=QUIET %s -p %d %s@%s -t \'%s\'',
            $this->getAuth($serverName),
            $port,
            $user,
            $host,
            $command
        );

        if (!$isQuiet) {
            $output->writeln($sshCommand);
        }

        [$exitCode, $scriptOutput] = $this->command->process($sshCommand, $isQuiet);

        if (0 !== $exitCode) {
            throw new ScriptException(sprintf('Error while executing script: %s', $command));
        }

        if (!is_string($scriptOutput)) {
            throw new ScriptException(sprintf('Invalid script output: %s', $scriptOutput));
        }

        foreach ($fileDownloads as $remoteFileName => $localFileName) {
            $this->copyFileFromHost(
                $output,
                $host,
                $port,
                $user,
                $remoteFileName,
                $localFileName,
                $isQuiet,
            );
        }

        return $scriptOutput;
    }

    public function download(
        OutputInterface $output,
        string $serverName,
        string $serverFileName,
        string $localFileName,
        bool $isQuiet
    ): void {
        throw new ScriptException('Download not implemented for OpenSSH server type.');
    }

    public function upload(
        OutputInterface $output,
        string $serverName,
        string $localFileName,
        string $serverFileName,
        bool $isQuiet
    ): void {
        $host = $this->server->getHost($serverName);
        $port = $this->server->getPort($serverName);
        $user = $this->server->getUser($serverName);

        $this->copyFileToHost($output, $host, $port, $user, $localFileName, $serverFileName, $isQuiet);
    }

    public function delete(OutputInterface $output, string $serverName, string $serverFileName, bool $isQuiet): void
    {
        $host = $this->server->getHost($serverName);
        $port = $this->server->getPort($serverName);
        $user = $this->server->getUser($serverName);

        if (!$isQuiet) {
            $output->writeln(sprintf('Deleting file at: %s@%s:%s', $user, $host, $serverFileName));
        }

        $sshCommand = sprintf(
            'ssh -o "StrictHostKeyChecking accept-new" -o LogLevel=QUIET %s -p %d %s@%s -t \'%s\'',
            $this->getAuth($serverName),
            $port,
            $user,
            $host,
            sprintf('rm -rf %s', $serverFileName)
        );

        if (!$isQuiet) {
            $output->writeln($sshCommand);
        }

        $this->command->process($sshCommand, $isQuiet);
    }

    private function copyFileToHost(
        OutputInterface $output,
        string $host,
        int $port,
        string $user,
        string $filePath,
        string $remoteFileName,
        bool $isQuiet,
        bool $isExecutable = false,
    ): void {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException(sprintf('File at: %s does not exist.', $filePath));
        }

        if (!$isQuiet) {
            $output->writeln(sprintf('Copying file from: %s to: %s@%s:%s', $filePath, $user, $host, $remoteFileName));
        }

        $sshCommand = sprintf(
            'scp -o "StrictHostKeyChecking accept-new" %s -P %d %s %s@%s:%s',
            $this->getAuth($host),
            $port,
            $filePath,
            $user,
            $host,
            $remoteFileName
        );

        if (!$isQuiet) {
            $output->writeln($sshCommand);
        }

        $this->command->process($sshCommand, $isQuiet);

        if ($isExecutable) {
            $sshCommand = sprintf(
                'ssh -o "StrictHostKeyChecking accept-new" -o LogLevel=QUIET %s -p %d %s@%s -t \'%s\'',
                $this->getAuth($host),
                $port,
                $user,
                $host,
                sprintf('chmod +x %s', $remoteFileName)
            );

            if (!$isQuiet) {
                $output->writeln($sshCommand);
            }

            $this->command->process($sshCommand, $isQuiet);
        }
    }

    private function copyFileFromHost(
        OutputInterface $output,
        string $host,
        int $port,
        string $user,
        string $remoteFileName,
        string $filePath,
        bool $isQuiet
    ): void {
        if (!$isQuiet) {
            $output->writeln(sprintf('Copying file from: %s@%s:%s to: %s', $user, $host, $remoteFileName, $filePath));
        }

        $sshCommand = sprintf(
            'scp -o "StrictHostKeyChecking accept-new" -o LogLevel=QUIET %s -P %d %s@%s:%s %s',
            $this->getAuth($host),
            $port,
            $user,
            $host,
            $remoteFileName,
            $filePath
        );

        if (!$isQuiet) {
            $output->writeln($sshCommand);
        }

        $this->command->process($sshCommand, $isQuiet);
    }

    private function getAuth(string $serverName): string
    {
        $auth = $this->server->getAuth($serverName);

        if ('file' === $auth) {
            $privateKeyFile = $this->server->getPrivateKeyFile($serverName);

            if (!file_exists($privateKeyFile)) {
                throw new ScriptException(sprintf('Private key file does not exist: %s', $privateKeyFile));
            }

            $sshAuth = sprintf('-i %s', $privateKeyFile);
        } else {
            $sshAuth = '';
        }

        return $sshAuth;
    }
}
