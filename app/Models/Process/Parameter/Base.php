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

    /**
     * @param array<string, array<int, string>|bool|string> $parameters
     *
     * @return array<string, array<int, string>|bool|string>
     */
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

    /**
     * @return array<int|string, string>
     */
    abstract protected function getServerValues(): array;

    /**
     * @return array<int|string, string>
     */
    abstract protected function getServerLists(): array;

    /**
     * @return array<int|string, string>
     */
    abstract protected function getComponentValues(): array;

    /**
     * @return array<int|string, string>
     */
    abstract protected function getComponentLists(): array;

    /**
     * @param array<string, array<int, string>|bool|string> $parameters
     * @param array<int|string, string>                     $values
     * @param array<int|string, string>                     $lists
     *
     * @return array<string, array<int, string>|bool|string>
     */
    protected function processParameters(
        array $parameters,
        string $sectionId,
        array $values,
        array $lists
    ): array {
        foreach ($values as $source => $target) {
            $default = null;

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

            if (count($list) > 0) {
                $listValues = [];

                foreach ($list as $listValue) {
                    if (is_string($listValue)) {
                        $listValues[] = $listValue;
                    }
                }

                $parameters[$target] = $implode ? implode(
                    ',',
                    $listValues
                ) : $listValues;
            }
        }

        return $parameters;
    }
}
