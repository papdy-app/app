<?php

declare(strict_types=1);

namespace App\Models\Command;

use App\Exceptions\ScriptException;
use App\Models\Config;
use App\Models\Path;
use FeWeDev\Base\Arrays;
use FeWeDev\Base\Variables;
use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SCP;
use phpseclib3\System\SSH\Agent;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class SSH extends Base
{
    public function __construct(
        protected Variables $variables,
        protected Arrays $arrays,
        protected Config $config,
        protected Path $path
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
        $scp = $this->getScp($serverName);

        $shell = $this->config->requiredValue($serverName, 'shell');

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

        $host = $this->getHost($serverName);
        $port = $this->getPort($serverName);
        $user = $this->getUser($serverName);

        $this->copyFileToHost(
            $output,
            $scp,
            $host,
            $port,
            $user,
            $parameterScriptPath,
            $parameterScriptRemotePath,
            true
        );

        $this->copyFileToHost(
            $output,
            $scp,
            $host,
            $port,
            $user,
            $command,
            basename($command),
            true
        );

        foreach ($fileUploadParameters as $fileParameter) {
            $file = $this->arrays->getValue($parameters, $fileParameter);

            if (!$this->variables->isEmpty($file)) {
                $file = $this->variables->stringValue($file);

                if (file_exists($file)) {
                    $this->copyFileToHost(
                        $output,
                        $scp,
                        $host,
                        $port,
                        $user,
                        $file,
                        basename($file)
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

        $command = $this->completeCommand(sprintf('./%s', basename($command)), $parameters);

        if (!$isQuiet) {
            $output->writeln($command);
        }

        $completeOutput = '';

        $scp->setTimeout(0);

        $scp->exec(
            "{$command} 2>&1 ; echo Exit status: $?",
            function (string $output) use (&$completeOutput, $isQuiet): void {
                if (!$isQuiet) {
                    echo $output;
                }

                $completeOutput .= $output;
            }
        );

        $completeOutput = rtrim($completeOutput, "\n");

        [$exitCode, $scriptOutput] = $this->processResult($completeOutput);

        if (0 !== $exitCode) {
            throw new ScriptException(sprintf('Error while executing script: %s', $command));
        }

        if (!is_string($scriptOutput)) {
            throw new ScriptException(sprintf('Invalid script output: %s', $scriptOutput));
        }

        foreach ($fileDownloads as $remoteFileName => $localFileName) {
            $this->copyFileFromHost(
                $output,
                $scp,
                $host,
                $port,
                $user,
                $remoteFileName,
                $localFileName
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
        $this->copyFileFromHost(
            $output,
            $this->getScp($serverName),
            $this->getHost($serverName),
            $this->getPort($serverName),
            $this->getUser($serverName),
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
        $this->copyFileToHost(
            $output,
            $this->getScp($serverName),
            $this->getHost($serverName),
            $this->getPort($serverName),
            $this->getUser($serverName),
            $localFileName,
            $serverFileName
        );
    }

    public function delete(OutputInterface $output, string $serverName, string $serverFileName, bool $isQuiet): void
    {
        $scp = $this->getScp($serverName);
        $host = $this->getHost($serverName);
        $user = $this->getUser($serverName);

        if (!$isQuiet) {
            $output->writeln(sprintf('Deleting file at: %s@%s:%s', $user, $host, $serverFileName));
        }

        $scp->exec(sprintf('rm -rf %s', $serverFileName));
    }

    private function getScp(string $serverName): SCP
    {
        $host = $this->getHost($serverName);
        $port = $this->getPort($serverName);
        $user = $this->getUser($serverName);

        $scp = new SCP($host, $port);

        $auth = $this->config->requiredValue($serverName, 'auth');

        if ('agent' === $auth) {
            $agent = new Agent();

            $result = $scp->login($user, $agent);
        } elseif ('password' === $auth) {
            $password = $this->config->requiredValue($serverName, 'password');

            $result = $scp->login($user, $password);
        } elseif ('key' === $auth) {
            $privateKey = $this->config->requiredValue($serverName, 'privateKey');

            $key = PublicKeyLoader::load($privateKey);

            if (!$key instanceof PrivateKey) {
                throw new ScriptException(sprintf('Invalid private key: %s', $privateKey));
            }

            $result = $scp->login($user, $key);
        } elseif ('file' === $auth) {
            $privateKeyFile = $this->config->requiredValue($serverName, 'privateKeyFile');

            if (!file_exists($privateKeyFile)) {
                throw new ScriptException(sprintf('Private key file does not exist: %s', $privateKeyFile));
            }

            $privateKeyContent = file_get_contents($privateKeyFile);

            if (false === $privateKeyContent) {
                throw new ScriptException(sprintf('Private key file could not be loaded from: %s', $privateKeyFile));
            }

            $key = PublicKeyLoader::load($privateKeyContent);

            if (!$key instanceof PrivateKey) {
                throw new ScriptException(sprintf('Invalid private key file: %s', $privateKeyFile));
            }

            $result = $scp->login($user, $key);
        } else {
            throw new ScriptException(sprintf('Unsupported authentication method: %s', $auth));
        }

        if (false === $result) {
            throw new ScriptException(
                sprintf('Could not authenticate with SSH agent to host: %s and port: %d.', $host, $port)
            );
        }

        return $scp;
    }

    private function getHost(string $serverName): string
    {
        return $this->config->requiredValue($serverName, 'host');
    }

    private function getPort(string $serverName): int
    {
        return intval($this->config->requiredValue($serverName, 'port'));
    }

    private function getUser(string $serverName): string
    {
        return $this->config->requiredValue($serverName, 'user');
    }

    private function copyFileToHost(
        OutputInterface $output,
        SCP $scp,
        string $host,
        int $port,
        string $user,
        string $filePath,
        string $remoteFileName,
        bool $isExecutable = false,
    ): void {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException(sprintf('File at: %s does not exist.', $filePath));
        }

        $output->writeln(sprintf('Copying file from: %s to: %s@%s:%s', $filePath, $user, $host, $remoteFileName));

        $result = $scp->put($remoteFileName, $filePath, SCP::SOURCE_LOCAL_FILE);

        if (false === $result) {
            throw new ScriptException(
                sprintf('Could not copy file: %s to SSH host: %s and port: %d.', $filePath, $host, $port)
            );
        }

        if ($isExecutable) {
            $scp->exec(sprintf('chmod +x %s', $remoteFileName));
        }
    }

    private function copyFileFromHost(
        OutputInterface $output,
        SCP $scp,
        string $host,
        int $port,
        string $user,
        string $remoteFileName,
        string $filePath,
    ): void {
        $output->writeln(sprintf('Copying file from: %s@%s:%s to: %s', $user, $host, $remoteFileName, $filePath));

        $result = $scp->get($remoteFileName, $filePath);

        if (false === $result) {
            throw new ScriptException(
                sprintf('Could not copy file: %s from SSH host: %s and port: %d.', $remoteFileName, $host, $port)
            );
        }

        $scp->exec(sprintf('chmod +x %s', $remoteFileName));
    }
}
