<?php

declare(strict_types=1);

namespace App\Models\Command;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Base
{
    abstract public function run(
        OutputInterface $output,
        string $serverName,
        string $scriptPath,
        array $parameters,
        bool $isQuiet
    ): string;

    protected function completeCommand(string $scriptPath, array $parameters): string
    {
        $command = $scriptPath;

        foreach ($parameters as $key => $value) {
            if (is_bool($value) && !$value) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $nextValue) {
                    $command .= sprintf(
                        ' --%s "%s"',
                        $key,
                        $nextValue
                    );
                }
            } else {
                $command .= sprintf(
                    ' --%s "%s"',
                    $key,
                    $value
                );
            }
        }

        return $command;
    }

    /**
     * @return array<int, null|int|string>
     */
    protected function processResult(string $completeOutput): array
    {
        // get exit status
        preg_match(
            '/[0-9]+$/',
            $completeOutput,
            $matches
        );

        // return exit status and intended output
        return is_array($matches) && array_key_exists(
            0,
            $matches
        ) ? [
            intval($matches[0]),
            preg_replace(
                '/[\r\n]$/',
                '',
                str_replace(
                    sprintf(
                        'Exit status: %s',
                        $matches[0]
                    ),
                    '',
                    $completeOutput
                )
            ),
        ] : [99, $completeOutput];
    }

    abstract public function download(
        OutputInterface $output,
        string $serverName,
        string $serverFileName,
        string $localFileName,
        bool $isQuiet
    ): void;

    abstract public function upload(
        OutputInterface $output,
        string $serverName,
        string $localFileName,
        string $serverFileName,
        bool $isQuiet
    ): void;
}
