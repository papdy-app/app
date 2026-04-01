<?php

declare(strict_types=1);

namespace App\Models\Process\Parameter;

use App\Models\Config;
use FeWeDev\Base\Variables;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Base
{
    public function __construct(protected Variables $variables, protected Config $config) {}

    public function execute(
        string $serverName,
        string $componentId,
        array $parameters
    ): array {
        $parameters = $this->processParameters(
            $parameters,
            $serverName,
            $this->getServerValues(),
            $this->getServerLists()
        );

        return $this->processParameters(
            $parameters,
            $componentId,
            $this->getComponentValues(),
            $this->getComponentLists()
        );
    }

    abstract protected function getServerValues(): array;

    abstract protected function getServerLists(): array;

    abstract protected function getComponentValues(): array;

    abstract protected function getComponentLists(): array;

    protected function processParameters(
        array $parameters,
        string $sectionId,
        array $values,
        array $lists
    ): array {
        foreach ($values as $source => $target) {
            $default = null;

            if (is_string($target)) {
                if (str_contains(
                    $target,
                    '|'
                )) {
                    [$target, $default] = explode(
                        '|',
                        $target
                    );
                }

                if (str_ends_with(
                    $target,
                    '?'
                )) {
                    $target = substr(
                        $target,
                        0,
                        -1
                    );

                    if (array_key_exists(
                        $target,
                        $parameters
                    )) {
                        continue;
                    }
                }
            }

            if (is_numeric($source)) {
                $source = $target;

                if (str_ends_with(
                    $target,
                    '*'
                )) {
                    $target = substr(
                        $target,
                        0,
                        -1
                    );
                }
            }

            if (str_ends_with(
                $source,
                '*'
            )) {
                $isRequired = true;
                $source = substr(
                    $source,
                    0,
                    -1
                );
            } else {
                $isRequired = false;
            }

            $value = $this->config->value(
                $sectionId,
                $source,
                $isRequired
            );

            if (!$this->variables->isEmpty($value)) {
                $parameters[$target] = $value;
            } elseif (null !== $default) {
                $parameters[$target] = $default;
            }
        }

        foreach ($lists as $source => $target) {
            if (is_numeric($source)) {
                $source = $target;

                if (str_ends_with(
                    $target,
                    '*'
                )) {
                    $target = substr(
                        $target,
                        0,
                        -1
                    );
                }
            }

            if (str_ends_with(
                $source,
                '*'
            )) {
                $isRequired = true;
                $source = substr(
                    $source,
                    0,
                    -1
                );
            } else {
                $isRequired = false;
            }

            if (str_ends_with(
                $target,
                '@'
            )) {
                $implode = false;
                $target = substr(
                    $target,
                    0,
                    -1
                );
            } else {
                $implode = true;
            }

            $list = $this->config->list(
                $sectionId,
                $source,
                $isRequired
            );

            if (!$this->variables->isEmpty($list)) {
                $parameters[$target] = $implode ? implode(
                    ',',
                    $list
                ) : $list;
            }
        }

        return $parameters;
    }
}
