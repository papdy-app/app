<?php

declare(strict_types=1);

namespace App\Models\Process;

use App\Exceptions\MissingConfigException;
use App\Exceptions\ScriptException;
use App\Exceptions\ServerNotFoundException;
use App\Models\Command\Local;
use App\Models\Command\Remote;
use App\Models\Command\SSH;
use App\Models\Config;
use App\Models\Path;
use FeWeDev\Base\Arrays;
use FeWeDev\Base\Strings;
use FeWeDev\Base\Variables;
use Illuminate\Contracts\Foundation\Application;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Base
{
    public function __construct(
        protected Variables $variables,
        protected Arrays $arrays,
        protected Strings $strings,
        protected Config $config,
        protected Application $app,
        protected Path $path,
        protected Local $local,
        protected Remote $remote,
        protected SSH $ssh
    ) {}

    protected function getServerName(?string $serverName, ?string $host, bool $isRequired = true): ?string
    {
        if ($this->variables->isEmpty($serverName)) {
            $serverList = $this->getServerList();

            foreach ($serverList as $server) {
                $serverType = $this->config->requiredValue($server, 'type');

                if ('local' === $serverType && ('localhost' === $host || '127.0.0.1' === $host)) {
                    $serverName = $server;
                } elseif ('local' !== $serverType) {
                    $serverHost = $this->config->requiredValue($server, 'host');

                    if ($serverHost === $host) {
                        $serverName = $server;
                    }
                }
            }
        }

        if ($this->variables->isEmpty($serverName) && $isRequired) {
            throw new ServerNotFoundException(sprintf('No server found for host: %s', $host));
        }

        return $serverName;
    }

    /**
     * @return array<int, string>
     */
    protected function getServerList(): array
    {
        $configServerList = $this->config->list('system', 'server', true);

        if (0 === count($configServerList)) {
            throw new MissingConfigException('No servers found!');
        }

        $serverList = [];

        foreach ($configServerList as $serverName) {
            if (is_string($serverName)) {
                $serverList[] = $serverName;
            }
        }

        return $serverList;
    }

    /**
     * @param array<int, string>                            $scriptPaths
     * @param array<int, string>                            $components
     * @param array<string, array<int, string>|bool|string> $parameters
     * @param array<int, string>                            $fileUploadParameters
     * @param array<int, string>                            $fileDownloadParameters
     * @param array<int, string>                            $preCommandParameters
     * @param array<int, string>                            $postCommandParameters
     */
    protected function runScript(
        OutputInterface $output,
        string $scriptName,
        array $scriptPaths,
        array $components,
        array $parameters,
        array $fileUploadParameters = [],
        array $fileDownloadParameters = [],
        array $preCommandParameters = [],
        array $postCommandParameters = [],
        bool $isQuiet = false
    ): string {
        $component = array_shift($components);

        if (null === $component) {
            throw new ScriptException('No components defined');
        }

        if (str_contains($component, ':')) {
            [$componentName, $componentMode] = explode(':', $component);
        } else {
            $componentName = $component;
            $componentMode = 'single';
        }

        if ('single' === $componentMode) {
            $serverList = $this->getServerList();

            foreach ($serverList as $serverName) {
                $componentId = $this->config->value($serverName, $componentName);

                if (!$this->variables->isEmpty($componentId)) {
                    $parameters = $this->prepareServerParameters(
                        $serverName,
                        $componentName,
                        $componentId,
                        $parameters
                    );

                    if (count($components) > 0) {
                        return $this->runScript(
                            $output,
                            $scriptName,
                            $scriptPaths,
                            $components,
                            $parameters,
                            $fileUploadParameters,
                            $fileDownloadParameters,
                            $preCommandParameters,
                            $postCommandParameters,
                            $isQuiet
                        );
                    }

                    return $this->executeRun(
                        $output,
                        $serverName,
                        $scriptName,
                        $scriptPaths,
                        $parameters,
                        $fileUploadParameters,
                        $fileDownloadParameters,
                        $preCommandParameters,
                        $postCommandParameters,
                        $isQuiet
                    );
                }
            }

            return '';
        }

        if ('all' === $componentMode) {
            $serverList = $this->getServerList();

            $hasAny = false;
            $allOutputs = [];

            foreach ($serverList as $serverName) {
                $componentId = $this->config->value($serverName, $componentName);

                if (!$this->variables->isEmpty($componentId)) {
                    $hasAny = true;

                    $parameters = $this->prepareServerParameters(
                        $serverName,
                        $componentName,
                        $componentId,
                        $parameters
                    );

                    if (count($components) > 0) {
                        return $this->runScript(
                            $output,
                            $scriptName,
                            $scriptPaths,
                            $components,
                            $parameters,
                            $fileUploadParameters,
                            $fileDownloadParameters,
                            $preCommandParameters,
                            $postCommandParameters,
                            $isQuiet
                        );
                    }

                    $allOutputs[] = $this->executeRun(
                        $output,
                        $serverName,
                        $scriptName,
                        $scriptPaths,
                        $parameters,
                        $fileUploadParameters,
                        $fileDownloadParameters,
                        $preCommandParameters,
                        $postCommandParameters,
                        $isQuiet
                    );
                }
            }

            if (!$hasAny) {
                throw new ScriptException(sprintf('No servers found for component: %s', $componentName));
            }

            return implode(PHP_EOL, $allOutputs);
        }

        throw new ScriptException(sprintf('Invalid run component: %s', $component));
    }

    /**
     * @param array<int, string> $components
     */
    protected function download(
        OutputInterface $output,
        string $serverFileName,
        string $localFileName,
        array $components,
        bool $isQuiet = false
    ): void {
        $component = array_shift($components);

        if (null === $component) {
            throw new ScriptException('No components defined');
        }

        if (str_contains($component, ':')) {
            [$componentName, $componentMode] = explode(':', $component);
        } else {
            $componentName = $component;
            $componentMode = 'single';
        }

        if ('single' === $componentMode) {
            $serverList = $this->getServerList();

            foreach ($serverList as $serverName) {
                $componentId = $this->config->value($serverName, $componentName);

                if (!$this->variables->isEmpty($componentId)) {
                    if (count($components) > 0) {
                        $this->download($output, $serverFileName, $localFileName, $components, $isQuiet);
                    } else {
                        $this->executeDownload($output, $serverName, $serverFileName, $localFileName, $isQuiet);
                    }

                    return;
                }
            }
        } elseif ('all' === $componentMode) {
            $serverList = $this->getServerList();

            $hasAny = false;

            foreach ($serverList as $serverName) {
                $componentId = $this->config->value($serverName, $componentName);

                if (!$this->variables->isEmpty($componentId)) {
                    $hasAny = true;

                    if (count($components) > 0) {
                        $this->download($output, $serverFileName, $localFileName, $components, $isQuiet);

                        return;
                    }

                    $this->executeDownload($output, $serverName, $serverFileName, $localFileName, $isQuiet);
                }
            }

            if (!$hasAny) {
                throw new ScriptException(sprintf('No servers found for component: %s', $componentName));
            }
        } else {
            throw new ScriptException(sprintf('Invalid download component: %s', $component));
        }
    }

    /**
     * @param array<int, string> $components
     */
    protected function upload(
        OutputInterface $output,
        string $localFileName,
        string $serverFileName,
        array $components,
        bool $isQuiet = false
    ): void {
        $component = array_shift($components);

        if (null === $component) {
            throw new ScriptException('No components defined');
        }

        if (str_contains($component, ':')) {
            [$componentName, $componentMode] = explode(':', $component);
        } else {
            $componentName = $component;
            $componentMode = 'single';
        }

        if ('single' === $componentMode) {
            $serverList = $this->getServerList();

            foreach ($serverList as $serverName) {
                $componentId = $this->config->value($serverName, $componentName);

                if (!$this->variables->isEmpty($componentId)) {
                    if (count($components) > 0) {
                        $this->upload($output, $localFileName, $serverFileName, $components, $isQuiet);
                    } else {
                        $this->executeUpload($output, $serverName, $localFileName, $serverFileName, $isQuiet);
                    }

                    return;
                }
            }
        } elseif ('all' === $componentMode) {
            $serverList = $this->getServerList();

            $hasAny = false;

            foreach ($serverList as $serverName) {
                $componentId = $this->config->value($serverName, $componentName);

                if (!$this->variables->isEmpty($componentId)) {
                    $hasAny = true;

                    if (count($components) > 0) {
                        $this->upload($output, $localFileName, $serverFileName, $components, $isQuiet);

                        return;
                    }

                    $this->executeUpload($output, $serverName, $localFileName, $serverFileName, $isQuiet);
                }
            }

            if (!$hasAny) {
                throw new ScriptException(sprintf('No servers found for component: %s', $componentName));
            }
        } else {
            throw new ScriptException(sprintf('Invalid download component: %s', $component));
        }
    }

    /**
     * @param array<string, array<int, string>|bool|string> $parameters
     *
     * @return array<string, array<int, string>|bool|string>
     */
    private function prepareServerParameters(
        string $serverName,
        string $componentName,
        string $componentId,
        array $parameters
    ): array {
        $bindingId = sprintf('process.parameters.%s', $componentName);

        if ($this->app->has($bindingId)) {
            try {
                /** @var Parameter\Base $componentParameters */
                $componentParameters = $this->app->get($bindingId);

                return $componentParameters->execute($serverName, $componentId, $parameters);
            } catch (ContainerExceptionInterface $exception) {
                throw new ScriptException($exception->getMessage(), Command::FAILURE, $exception);
            }
        }

        return $parameters;
    }

    /**
     * @param array<int, string>                            $scriptPaths
     * @param array<string, array<int, string>|bool|string> $parameters
     * @param array<int, string>                            $fileUploadParameters
     * @param array<int, string>                            $fileDownloadParameters
     * @param array<int, string>                            $preCommandParameters
     * @param array<int, string>                            $postCommandParameters
     */
    private function executeRun(
        OutputInterface $output,
        string $serverName,
        string $scriptName,
        array $scriptPaths,
        array $parameters,
        array $fileUploadParameters,
        array $fileDownloadParameters,
        array $preCommandParameters,
        array $postCommandParameters,
        bool $isQuiet
    ): string {
        $shell = $this->config->requiredValue($serverName, 'shell');

        $shellFullPath = sprintf(
            '%s%s%s%s%s',
            $this->path->getBasePath(),
            DIRECTORY_SEPARATOR,
            'scripts',
            DIRECTORY_SEPARATOR,
            $shell
        );

        $scriptFullPath = null;

        foreach ($scriptPaths as $key => $scriptPath) {
            $scriptPaths[$key] = $this->arrays->getValue($parameters, $scriptPath, $scriptPath);
        }

        for ($i = count($scriptPaths); $i > 0; --$i) {
            $scriptPathsSlice = $this->arrays->strings(array_slice($scriptPaths, 0, $i));

            $scriptFullPath = sprintf(
                '%s%s%s%s%s',
                $shellFullPath,
                DIRECTORY_SEPARATOR,
                implode(DIRECTORY_SEPARATOR, $scriptPathsSlice),
                DIRECTORY_SEPARATOR,
                $scriptName
            );

            if (file_exists($scriptFullPath)) {
                break;
            }

            $scriptFullPath = null;
        }

        if (null === $scriptFullPath) {
            $scriptFullPath = sprintf('%s%s%s', $shellFullPath, DIRECTORY_SEPARATOR, $scriptName);

            if (!file_exists($scriptFullPath)) {
                throw new ScriptException(sprintf('Script not found: %s', $scriptFullPath));
            }
        }

        return $this->executeCommand(
            $output,
            $serverName,
            $scriptFullPath,
            $parameters,
            $fileUploadParameters,
            $fileDownloadParameters,
            $preCommandParameters,
            $postCommandParameters,
            $isQuiet
        );
    }

    /**
     * @param array<string, array<int, string>|bool|string> $parameters
     * @param array<int, string>                            $fileUploadParameters
     * @param array<int, string>                            $fileDownloadParameters
     * @param array<int, string>                            $preCommandParameters
     * @param array<int, string>                            $postCommandParameters
     */
    private function executeCommand(
        OutputInterface $output,
        string $serverName,
        string $command,
        array $parameters,
        array $fileUploadParameters,
        array $fileDownloadParameters,
        array $preCommandParameters,
        array $postCommandParameters,
        bool $isQuiet
    ): string {
        $serverType = $this->config->requiredValue($serverName, 'type');

        if ('local' === $serverType) {
            $environment = $this->local;
        } elseif ('remote' === $serverType) {
            $environment = $this->remote;
        } elseif ('ssh' === $serverType) {
            $environment = $this->ssh;
        } else {
            throw new ScriptException(sprintf('Unsupported server type: %s', $serverType));
        }

        foreach ($preCommandParameters as $preCommandParameter) {
            $preCommands = $this->arrays->getValue($parameters, $preCommandParameter);

            if (!$this->variables->isEmpty($preCommands)) {
                if (!is_array($preCommands)) {
                    $preCommands = [$preCommands];
                }

                foreach ($preCommands as $preCommand) {
                    $preCommand = $this->strings->replacePlaceHolders(
                        $this->variables->stringValue($preCommand),
                        $parameters
                    );

                    $environment->run($output, $serverName, $preCommand, [], [], [], $isQuiet);
                }

                unset($parameters[$preCommandParameter]);
            }
        }

        $allPostCommands = [];

        foreach ($postCommandParameters as $postCommandParameter) {
            $postCommands = $this->arrays->getValue($parameters, $postCommandParameter);

            if (!$this->variables->isEmpty($postCommands)) {
                if (!is_array($postCommands)) {
                    $postCommands = [$postCommands];
                }

                foreach ($postCommands as $postCommand) {
                    if (!$this->variables->isEmpty($postCommand)) {
                        $allPostCommands[] = $postCommand;
                    }
                }

                unset($parameters[$postCommandParameter]);
            }
        }

        $scriptOutput = $environment->run(
            $output,
            $serverName,
            $command,
            $parameters,
            $fileUploadParameters,
            $fileDownloadParameters,
            $isQuiet
        );

        foreach ($allPostCommands as $postCommand) {
            $postCommand = $this->strings->replacePlaceHolders(
                $this->variables->stringValue($postCommand),
                $parameters
            );

            $environment->run($output, $serverName, $postCommand, [], [], [], $isQuiet);
        }

        return $scriptOutput;
    }

    private function executeDownload(
        OutputInterface $output,
        string $serverName,
        string $serverFileName,
        string $localFileName,
        bool $isQuiet
    ): void {
        $serverType = $this->config->requiredValue($serverName, 'type');

        if ('local' === $serverType) {
            $this->local->download($output, $serverName, $serverFileName, $localFileName, $isQuiet);
        } elseif ('remote' === $serverType) {
            $this->remote->download($output, $serverName, $serverFileName, $localFileName, $isQuiet);
        } elseif ('ssh' === $serverType) {
            $this->ssh->download($output, $serverName, $serverFileName, $localFileName, $isQuiet);
        } else {
            throw new ScriptException(sprintf('Unsupported server type: %s', $serverType));
        }
    }

    private function executeUpload(
        OutputInterface $output,
        string $serverName,
        string $localFileName,
        string $serverFileName,
        bool $isQuiet
    ): void {
        $serverType = $this->config->requiredValue($serverName, 'type');

        if ('local' === $serverType) {
            $this->local->upload($output, $serverName, $localFileName, $serverFileName, $isQuiet);
        } elseif ('remote' === $serverType) {
            $this->remote->upload($output, $serverName, $localFileName, $serverFileName, $isQuiet);
        } elseif ('ssh' === $serverType) {
            $this->ssh->upload($output, $serverName, $localFileName, $serverFileName, $isQuiet);
        } else {
            throw new ScriptException(sprintf('Unsupported server type: %s', $serverType));
        }
    }
}
