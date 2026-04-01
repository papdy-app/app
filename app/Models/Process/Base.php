<?php

declare(strict_types=1);

namespace App\Models\Process;

use App\Exceptions\MissingConfigException;
use App\Exceptions\ScriptException;
use App\Exceptions\ServerNotFoundException;
use App\Models\Command\Local;
use App\Models\Command\SSH;
use App\Models\Config;
use FeWeDev\Base\Variables;
use Illuminate\Contracts\Foundation\Application;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
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
        protected Config $config,
        protected Application $app,
        protected Local $local,
        protected SSH $ssh
    ) {}

    protected function getServerName(?string $serverName, ?string $host, bool $isRequired = true): ?string
    {
        if ($this->variables->isEmpty($serverName)) {
            $serverList = $this->getServerList();

            foreach ($serverList as $server) {
                $serverType = $this->config->requiredValue(
                    $server,
                    'type'
                );

                if ('local' === $serverType && ('localhost' === $host || '127.0.0.1' === $host)) {
                    $serverName = $server;
                } elseif ('local' !== $serverType) {
                    $serverHost = $this->config->requiredValue(
                        $server,
                        'host'
                    );

                    if ($serverHost === $host) {
                        $serverName = $server;
                    }
                }
            }
        }

        if ($this->variables->isEmpty($serverName) && $isRequired) {
            throw new ServerNotFoundException(
                sprintf(
                    'No server found for host: %s',
                    $host
                )
            );
        }

        return $serverName;
    }

    /**
     * @return array<string>
     */
    protected function getServerList(): array
    {
        $serverList = $this->config->list(
            'system',
            'server',
            true
        );

        if ($this->variables->isEmpty($serverList)) {
            throw new MissingConfigException('No servers found!');
        }

        return $serverList;
    }

    protected function run(
        OutputInterface $output,
        string $scriptName,
        array $components,
        array $parameters,
        bool $isQuiet = false
    ): string {
        $component = array_shift($components);

        if (str_contains(
            $component,
            ':'
        )) {
            [$componentName, $componentMode] = explode(
                ':',
                $component
            );
        } else {
            $componentName = $component;
            $componentMode = 'single';
        }

        if ('single' === $componentMode) {
            $serverList = $this->getServerList();

            foreach ($serverList as $serverName) {
                $componentId = $this->config->value(
                    $serverName,
                    $componentName
                );

                if (!$this->variables->isEmpty($componentId)) {
                    $parameters = $this->prepareServerParameters(
                        $serverName,
                        $componentName,
                        $componentId,
                        $parameters
                    );

                    if (count($components) > 0) {
                        return $this->run(
                            $output,
                            $scriptName,
                            $components,
                            $parameters,
                            $isQuiet
                        );
                    } else {
                        return $this->executeRun(
                            $output,
                            $serverName,
                            $scriptName,
                            $parameters,
                            $isQuiet
                        );
                    }
                }
            }

            return '';
        } elseif ('all' === $componentMode) {
            $serverList = $this->getServerList();

            $hasAny = false;
            $allOutputs = [];

            foreach ($serverList as $serverName) {
                $componentId = $this->config->value(
                    $serverName,
                    $componentName
                );

                if (!$this->variables->isEmpty($componentId)) {
                    $hasAny = true;

                    $parameters = $this->prepareServerParameters(
                        $serverName,
                        $componentName,
                        $componentId,
                        $parameters
                    );

                    if (count($components) > 0) {
                        return $this->run(
                            $output,
                            $scriptName,
                            $components,
                            $parameters,
                            $isQuiet
                        );
                    } else {
                        $allOutputs[] = $this->executeRun(
                            $output,
                            $serverName,
                            $scriptName,
                            $parameters,
                            $isQuiet
                        );
                    }
                }
            }

            if (!$hasAny) {
                throw new ScriptException(
                    sprintf(
                        'No servers found for component: %s',
                        $componentName
                    )
                );
            }

            return implode(
                PHP_EOL,
                $allOutputs
            );
        } else {
            throw new ScriptException(
                sprintf(
                    'Invalid run component: %s',
                    $component
                )
            );
        }
    }

    private function prepareServerParameters(
        string $serverName,
        string $componentName,
        string $componentId,
        array $parameters
    ): array {
        $bindingId = sprintf(
            'process.parameters.%s',
            $componentName
        );

        if ($this->app->has($bindingId)) {
            try {
                /** @var Parameter\Base $componentParameters */
                $componentParameters = $this->app->get($bindingId);

                return $componentParameters->execute(
                    $serverName,
                    $componentId,
                    $parameters
                );
            } catch (NotFoundExceptionInterface|ContainerExceptionInterface $exception) {
                throw new ScriptException(
                    $exception->getMessage(),
                    Command::FAILURE,
                    $exception
                );
            }
        }

        return $parameters;
    }

    private function executeRun(
        OutputInterface $output,
        string $serverName,
        string $scriptName,
        array $parameters,
        bool $isQuiet
    ): string {
        $scriptPath = sprintf(
            '%s%s%s%s%s',
            base_path(),
            DIRECTORY_SEPARATOR,
            'scripts',
            DIRECTORY_SEPARATOR,
            $scriptName
        );

        if (!file_exists($scriptPath)) {
            throw new ScriptException(
                sprintf(
                    'Script not found: %s',
                    $scriptPath
                )
            );
        }

        $serverType = $this->config->requiredValue(
            $serverName,
            'type'
        );

        if ('local' === $serverType) {
            $scriptOutput = $this->local->run(
                $output,
                $serverName,
                $scriptPath,
                $parameters,
                $isQuiet
            );
        } elseif ('ssh' === $serverType) {
            $scriptOutput = $this->ssh->run(
                $output,
                $serverName,
                $scriptPath,
                $parameters,
                $isQuiet
            );
        } else {
            throw new ScriptException(
                sprintf(
                    'Unsupported server type: %s',
                    $serverType
                )
            );
        }

        return $scriptOutput;
    }

    protected function download(
        OutputInterface $output,
        string $serverFileName,
        string $localFileName,
        array $components,
        bool $isQuiet = false
    ): void {
        $component = array_shift($components);

        if (str_contains(
            $component,
            ':'
        )) {
            [$componentName, $componentMode] = explode(
                ':',
                $component
            );
        } else {
            $componentName = $component;
            $componentMode = 'single';
        }

        if ('single' === $componentMode) {
            $serverList = $this->getServerList();

            foreach ($serverList as $serverName) {
                $componentId = $this->config->value(
                    $serverName,
                    $componentName
                );

                if (!$this->variables->isEmpty($componentId)) {
                    if (count($components) > 0) {
                        $this->download(
                            $output,
                            $serverFileName,
                            $localFileName,
                            $components,
                            $isQuiet
                        );
                    } else {
                        $this->executeDownload(
                            $output,
                            $serverName,
                            $serverFileName,
                            $localFileName,
                            $isQuiet
                        );
                    }

                    return;
                }
            }
        } elseif ('all' === $componentMode) {
            $serverList = $this->getServerList();

            $hasAny = false;

            foreach ($serverList as $serverName) {
                $componentId = $this->config->value(
                    $serverName,
                    $componentName
                );

                if (!$this->variables->isEmpty($componentId)) {
                    $hasAny = true;

                    if (count($components) > 0) {
                        $this->download(
                            $output,
                            $serverFileName,
                            $localFileName,
                            $components,
                            $isQuiet
                        );

                        return;
                    } else {
                        $this->executeDownload(
                            $output,
                            $serverName,
                            $serverFileName,
                            $localFileName,
                            $isQuiet
                        );
                    }
                }
            }

            if (!$hasAny) {
                throw new ScriptException(
                    sprintf(
                        'No servers found for component: %s',
                        $componentName
                    )
                );
            }
        } else {
            throw new ScriptException(
                sprintf(
                    'Invalid download component: %s',
                    $component
                )
            );
        }
    }

    private function executeDownload(
        OutputInterface $output,
        string $serverName,
        string $serverFileName,
        string $localFileName,
        bool $isQuiet
    ): void {
        $serverType = $this->config->requiredValue(
            $serverName,
            'type'
        );

        if ('local' === $serverType) {
            $this->local->download(
                $output,
                $serverName,
                $serverFileName,
                $localFileName,
                $isQuiet
            );
        } elseif ('ssh' === $serverType) {
            $this->ssh->download(
                $output,
                $serverName,
                $serverFileName,
                $localFileName,
                $isQuiet
            );
        } else {
            throw new ScriptException(
                sprintf(
                    'Unsupported server type: %s',
                    $serverType
                )
            );
        }
    }

    protected function upload(
        OutputInterface $output,
        string $localFileName,
        string $serverFileName,
        array $components,
        bool $isQuiet = false
    ): void {
        $component = array_shift($components);

        if (str_contains(
            $component,
            ':'
        )) {
            [$componentName, $componentMode] = explode(
                ':',
                $component
            );
        } else {
            $componentName = $component;
            $componentMode = 'single';
        }

        if ('single' === $componentMode) {
            $serverList = $this->getServerList();

            foreach ($serverList as $serverName) {
                $componentId = $this->config->value(
                    $serverName,
                    $componentName
                );

                if (!$this->variables->isEmpty($componentId)) {
                    if (count($components) > 0) {
                        $this->upload(
                            $output,
                            $localFileName,
                            $serverFileName,
                            $components,
                            $isQuiet
                        );
                    } else {
                        $this->executeUpload(
                            $output,
                            $serverName,
                            $localFileName,
                            $serverFileName,
                            $isQuiet
                        );
                    }

                    return;
                }
            }
        } elseif ('all' === $componentMode) {
            $serverList = $this->getServerList();

            $hasAny = false;

            foreach ($serverList as $serverName) {
                $componentId = $this->config->value(
                    $serverName,
                    $componentName
                );

                if (!$this->variables->isEmpty($componentId)) {
                    $hasAny = true;

                    if (count($components) > 0) {
                        $this->upload(
                            $output,
                            $localFileName,
                            $serverFileName,
                            $components,
                            $isQuiet
                        );

                        return;
                    } else {
                        $this->executeUpload(
                            $output,
                            $serverName,
                            $localFileName,
                            $serverFileName,
                            $isQuiet
                        );
                    }
                }
            }

            if (!$hasAny) {
                throw new ScriptException(
                    sprintf(
                        'No servers found for component: %s',
                        $componentName
                    )
                );
            }
        } else {
            throw new ScriptException(
                sprintf(
                    'Invalid download component: %s',
                    $component
                )
            );
        }
    }

    private function executeUpload(
        OutputInterface $output,
        string $serverName,
        string $localFileName,
        string $serverFileName,
        bool $isQuiet
    ): void {
        $serverType = $this->config->requiredValue(
            $serverName,
            'type'
        );

        if ('local' === $serverType) {
            $this->local->upload(
                $output,
                $serverName,
                $localFileName,
                $serverFileName,
                $isQuiet
            );
        } elseif ('ssh' === $serverType) {
            $this->ssh->upload(
                $output,
                $serverName,
                $localFileName,
                $serverFileName,
                $isQuiet
            );
        } else {
            throw new ScriptException(
                sprintf(
                    'Unsupported server type: %s',
                    $serverType
                )
            );
        }
    }
}
