<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidConfigurationException;
use App\Exceptions\MissingConfigException;
use FeWeDev\Base\Arrays;
use Matomo\Ini\IniReader;
use Matomo\Ini\IniReadingException;
use Matomo\Ini\IniWriter;
use Matomo\Ini\IniWritingException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Config
{
    public const FILE_NAME = 'env.ini';

    /** @var array<string, array<string, array<string, int|string>|int|string>> */
    private array $config = [];

    private bool $hasChanges = false;

    /**
     * @throws IniReadingException
     */
    public function __construct(protected Arrays $arrays)
    {
        $this->load();
    }

    /**
     * @throws IniWritingException
     */
    public function __destruct()
    {
        $this->flush();
    }

    /**
     * @throws IniReadingException
     */
    public function load(): void
    {
        if (file_exists($this->getFileName())) {
            $reader = new IniReader();

            $this->config = $this->checkConfig($reader->readFile($this->getFileName()));
        }
    }

    /**
     * @throws IniWritingException
     */
    public function flush(): void
    {
        if ($this->hasChanges) {
            if (count($this->config) > 0) {
                $writer = new IniWriter();

                $writer->writeToFile(
                    $this->getFileName(),
                    $this->config
                );
            } elseif (file_exists($this->getFileName())) {
                unlink($this->getFileName());
            }
        }
    }

    public function value(string $section, string $key, bool $isRequired = false): ?string
    {
        $value = $this->arrays->getValue(
            $this->config,
            sprintf(
                '%s:%s',
                $section,
                $key
            )
        );

        if (null !== $value || !$isRequired) {
            return is_scalar($value) ? strval($value) : null;
        }

        throw new MissingConfigException(
            sprintf(
                'Missing configuration with section: %s and key: %s',
                $section,
                $key
            )
        );
    }

    public function requiredValue(string $section, string $key): string
    {
        $value = $this->value(
            $section,
            $key,
            true
        );

        if (null === $value) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Missing required configuration with section: %s and key: %s',
                    $section,
                    $key
                )
            );
        }

        return $value;
    }

    /**
     * @return array<mixed, mixed>
     */
    public function list(string $section, string $key, bool $isRequired = false): array
    {
        $list = $this->arrays->getValue(
            $this->config,
            sprintf(
                '%s:%s',
                $section,
                $key
            ),
            []
        );

        if (null !== $list || !$isRequired) {
            return is_array($list) ? $list : [$list];
        }

        throw new MissingConfigException(
            sprintf(
                'Missing configuration with section: %s and key: %s',
                $section,
                $key
            )
        );
    }

    public function add(string $section, string $key, string $value): void
    {
        $this->config = $this->checkConfig(
            $this->arrays->addDeepValue(
                $this->config,
                [$section, $key],
                $value,
                false,
                true,
                false,
                true
            )
        );

        $this->hasChanges = true;
    }

    public function set(string $section, string $key, string $value): void
    {
        $this->config = $this->checkConfig(
            $this->arrays->addDeepValue(
                $this->config,
                [$section, $key],
                $value
            )
        );

        $this->hasChanges = true;
    }

    public function remove(string $section, string $key, ?string $value): void
    {
        $sectionExists = array_key_exists(
            $section,
            $this->config
        );

        if ($sectionExists) {
            $sectionData = $this->config[$section];

            $keyExists = array_key_exists(
                $key,
                $sectionData
            );

            if ($keyExists) {
                if (null !== $value) {
                    $currentValue = $this->config[$section][$key];

                    if (is_array($currentValue)) {
                        $valueKey = array_search(
                            $value,
                            $currentValue
                        );

                        if (false !== $valueKey) {
                            unset($this->config[$section][$key][$valueKey]);
                        }

                        if (0 === count($this->config[$section][$key])) {
                            unset($this->config[$section][$key]);
                        }

                        if (1 === count($this->config[$section][$key])) {
                            $this->config[$section][$key] = current($this->config[$section][$key]);
                            $this->hasChanges = true;
                        }
                    } elseif ($currentValue == $value) {
                        unset($this->config[$section][$key]);
                    }
                } else {
                    unset($this->config[$section][$key]);
                }
            }
        }
    }

    private function getFileName(): string
    {
        return base_path().DIRECTORY_SEPARATOR.self::FILE_NAME;
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<string, array<string, array<string, int|string>|int|string>>
     */
    private function checkConfig(array $config): array
    {
        $checkedConfig = [];

        foreach ($config as $section => $sectionData) {
            if (!is_array($sectionData)) {
                throw new InvalidConfigurationException(
                    sprintf(
                        'Invalid data in section: %s',
                        $section
                    )
                );
            }

            foreach ($sectionData as $key => $value) {
                if (!is_int($value) && !is_string($value) && !is_array($value)) {
                    throw new InvalidConfigurationException(
                        sprintf(
                            'Invalid data in section: %s and key: %s',
                            $section,
                            $key
                        )
                    );
                }

                if (is_array($value)) {
                    $checkedConfig[strval($section)][strval($key)] = [];

                    foreach ($value as $subKey => $subValue) {
                        if (!is_int($subValue) && !is_string($subValue)) {
                            throw new InvalidConfigurationException(
                                sprintf(
                                    'Invalid data in section: %s and key: %s',
                                    $section,
                                    $key
                                )
                            );
                        }

                        $checkedConfig[strval($section)][strval($key)][strval($subKey)] = $subValue;
                    }
                } else {
                    $checkedConfig[strval($section)][strval($key)] = $value;
                }
            }
        }

        return $checkedConfig;
    }
}
