<?php

declare(strict_types=1);

namespace App\Commands;

use App\Exceptions\CommandException;
use App\Exceptions\InputOptionException;
use App\Models\Config;
use FeWeDev\Base\Variables;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Helper\DescriptorHelper;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Base extends Command
{
    public function __construct(protected Variables $variables, protected Config $config)
    {
        $this->signature = count($this->getCommandParameters()) > 0 ? sprintf(
            '%s {%s}',
            $this->getCommandName(),
            implode(
                '} {',
                $this->getCommandParameters()
            )
        ) : $this->getCommandName();

        $this->description = $this->getCommandDescription();

        parent::__construct();
    }

    public function handle(): int
    {
        try {
            return $this->executeCommand();
        } catch (CommandException $exception) {
            return $this->exitWithError(
                $exception->getMessage(),
                $exception->getCode()
            );
        }
    }

    abstract protected function getCommandName(): string;

    abstract protected function getCommandDescription(): string;

    /**
     * @return array<int, string>
     */
    abstract protected function getCommandParameters(): array;

    abstract protected function executeCommand(): int;

    protected function exitWithError(string $message, int $resultCode = self::FAILURE): int
    {
        $this->error($message);
        $this->output->writeln('');

        $helper = new DescriptorHelper();
        $helper->describe(
            $this->output,
            $this
        );

        return $resultCode;
    }

    /**
     * @throws InputOptionException
     */
    protected function getOption(string $name, bool $isRequired = false, ?string $errorMessage = null): ?string
    {
        $value = $this->option($name);

        if ($isRequired && $this->variables->isEmpty($value)) {
            if (null === $errorMessage) {
                $errorMessage = sprintf(
                    'The "%s" option is required.',
                    $name
                );
            }

            throw new InputOptionException($errorMessage);
        }

        if (is_array($value)) {
            throw new InputOptionException(
                sprintf(
                    'The option "%s" in invalid.',
                    $name
                )
            );
        }

        return null === $value ? $value : strval($value);
    }

    protected function getRequiredOption(string $name, string $errorMessage): string
    {
        $option = $this->getOption(
            $name,
            true,
            $errorMessage
        );

        if (null === $option) {
            throw new InputOptionException($errorMessage);
        }

        return $option;
    }

    protected function getFlag(string $name): bool
    {
        return (bool) $this->option($name);
    }

    protected function isEmptyValue(mixed $value): bool
    {
        return $this->variables->isEmpty($value) || '-' === $value;
    }
}
