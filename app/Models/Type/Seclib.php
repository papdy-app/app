<?php

declare(strict_types=1);

namespace App\Models\Type;

use App\Exceptions\ScriptException;
use App\Models\Ssh\Connection;
use App\Models\Ssh\PrivateKey\PrivateKeyLoader;
use App\Models\Ssh\SshException;
use App\Services\Command;
use App\Services\Path;
use App\Services\Server;
use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use Random\RandomException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Seclib implements Type
{
    public function __construct(
        protected Variables $variables,
        protected Arrays $arrays,
        protected Path $path,
        protected Server $server,
        protected Command $command,
    ) {}

    /**
     * @param array<string, array<int, string>|bool|string> $parameters
     * @param array<int, string>                            $fileUploadParameters
     * @param array<int, string>                            $fileDownloadParameters
     */
    public function run(
        OutputInterface $output,
        string $serverName,
        string $command,
        array $parameters,
        array $fileUploadParameters,
        array $fileDownloadParameters,
        bool $isQuiet
    ): string {
        try {
            $connection = $this->getConnection($serverName);
        } catch (RandomException|\SodiumException $exception) {
            throw new ScriptException(sprintf('Could not connect to SSH because: %s', $exception->getMessage()));
        }

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
            $connection,
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
            $connection,
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
                        $connection,
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

        $completeOutput = '';

        try {
            $connection->exec(
                "{$command} 2>&1 ; echo Exit status: $?",
                function (string $chunk) use (&$completeOutput, $isQuiet): void {
                    if (!$isQuiet) {
                        echo $chunk;
                    }

                    $completeOutput .= $chunk;
                }
            );
        } catch (RandomException $exception) {
            throw new ScriptException(sprintf('Invalid script output: %s', $exception->getMessage()));
        }

        $completeOutput = rtrim($completeOutput, "\n");

        [$exitCode, $scriptOutput] = $this->command->processResult($completeOutput);

        if (0 !== $exitCode) {
            throw new ScriptException(sprintf('Error while executing script: %s', $command));
        }

        if (!is_string($scriptOutput)) {
            throw new ScriptException(sprintf('Invalid script output: %s', $scriptOutput));
        }

        foreach ($fileDownloads as $remoteFileName => $localFileName) {
            $this->copyFileFromHost(
                $output,
                $connection,
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
        try {
            $connection = $this->getConnection($serverName);
        } catch (RandomException|\SodiumException $exception) {
            throw new ScriptException(sprintf('Could not connect to SSH because: %s', $exception->getMessage()));
        }

        $this->copyFileFromHost(
            $output,
            $connection,
            $this->server->getHost($serverName),
            $this->server->getPort($serverName),
            $this->server->getUser($serverName),
            $serverFileName,
            $localFileName,
            $isQuiet,
        );
    }

    public function upload(
        OutputInterface $output,
        string $serverName,
        string $localFileName,
        string $serverFileName,
        bool $isQuiet
    ): void {
        try {
            $connection = $this->getConnection($serverName);
        } catch (RandomException|\SodiumException $exception) {
            throw new ScriptException(sprintf('Could not connect to SSH because: %s', $exception->getMessage()));
        }

        $this->copyFileToHost(
            $output,
            $connection,
            $this->server->getHost($serverName),
            $this->server->getPort($serverName),
            $this->server->getUser($serverName),
            $localFileName,
            $serverFileName,
            $isQuiet,
        );
    }

    public function delete(OutputInterface $output, string $serverName, string $serverFileName, bool $isQuiet): void
    {
        try {
            $connection = $this->getConnection($serverName);
        } catch (RandomException|\SodiumException $exception) {
            throw new ScriptException(sprintf('Could not connect to SSH because: %s', $exception->getMessage()));
        }

        $host = $this->server->getHost($serverName);
        $user = $this->server->getUser($serverName);

        if (!$isQuiet) {
            $output->writeln(sprintf('Deleting file at: %s@%s:%s', $user, $host, $serverFileName));
        }

        try {
            $connection->exec(sprintf('rm -rf %s', $serverFileName), function (string $output): void {});
        } catch (RandomException $exception) {
            $output->writeln(
                sprintf(
                    'Failed to delete file at: %s@%s:%s because: %s',
                    $user,
                    $host,
                    $serverFileName,
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * @throws RandomException
     * @throws \SodiumException
     */
    private function getConnection(string $serverName): Connection
    {
        $host = $this->server->getHost($serverName);
        $port = $this->server->getPort($serverName);
        $user = $this->server->getUser($serverName);

        $connection = new Connection();
        $connection->connect($host, $port);

        $auth = $this->server->getAuth($serverName);

        try {
            if ('agent' === $auth) {
                $result = $connection->authenticateWithAgent($user);
            } elseif ('password' === $auth) {
                $password = $this->server->getPassword($serverName);

                $result = $connection->authenticateWithPassword($user, $password);
            } elseif ('key' === $auth) {
                $privateKey = $this->server->getPrivateKey($serverName);

                $result = $connection->authenticateWithPublicKey($user, PrivateKeyLoader::load($privateKey));
            } elseif ('file' === $auth) {
                $privateKeyFile = $this->server->getPrivateKeyFile($serverName);

                if (!file_exists($privateKeyFile)) {
                    throw new ScriptException(sprintf('Private key file does not exist: %s', $privateKeyFile));
                }

                $privateKeyContent = file_get_contents($privateKeyFile);

                if (false === $privateKeyContent) {
                    throw new ScriptException(
                        sprintf('Private key file could not be loaded from: %s', $privateKeyFile)
                    );
                }

                $result = $connection->authenticateWithPublicKey(
                    $user,
                    PrivateKeyLoader::load($privateKeyContent)
                );
            } else {
                throw new ScriptException(sprintf('Unsupported authentication method: %s', $auth));
            }
        } catch (SshException $exception) {
            throw new ScriptException(
                sprintf('Could not connect via SSH to host: %s and port: %d.', $host, $port),
                0,
                $exception
            );
        }

        if (false === $result) {
            throw new ScriptException(
                sprintf('Could not authenticate with SSH agent to host: %s and port: %d.', $host, $port)
            );
        }

        return $connection;
    }

    private function copyFileToHost(
        OutputInterface $output,
        Connection $connection,
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

        try {
            $connection->scp()->upload($filePath, $remoteFileName);
        } catch (RandomException|SshException $exception) {
            throw new ScriptException(
                sprintf('Could not copy file: %s to SSH host: %s and port: %d.', $filePath, $host, $port),
                0,
                $exception
            );
        }

        if ($isExecutable) {
            try {
                $connection->exec(sprintf('chmod +x %s', $remoteFileName), function (string $output): void {});
            } catch (RandomException $exception) {
                throw new ScriptException(
                    sprintf('Could not chmod file: %s on SSH host: %s and port: %d.', $remoteFileName, $host, $port),
                    0,
                    $exception
                );
            }
        }
    }

    private function copyFileFromHost(
        OutputInterface $output,
        Connection $connection,
        string $host,
        int $port,
        string $user,
        string $remoteFileName,
        string $filePath,
        bool $isQuiet,
    ): void {
        if (!$isQuiet) {
            $output->writeln(sprintf('Copying file from: %s@%s:%s to: %s', $user, $host, $remoteFileName, $filePath));
        }

        try {
            $connection->scp()->download($remoteFileName, $filePath);
        } catch (RandomException|SshException $exception) {
            throw new ScriptException(
                sprintf('Could not copy file: %s from SSH host: %s and port: %d.', $remoteFileName, $host, $port),
                0,
                $exception
            );
        }
    }
}
