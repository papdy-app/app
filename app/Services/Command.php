<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ScriptException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Command
{
    /**
     * @param array<string, array<int, string>|bool|string> $parameters
     */
    public function completeCommand(string $scriptPath, array $parameters): string
    {
        $command = $scriptPath;

        foreach ($parameters as $key => $value) {
            if (is_bool($value) && !$value) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $nextValue) {
                    $command .= sprintf(' --%s "%s"', $key, $nextValue);
                }
            } elseif (is_bool($value)) {
                $command .= sprintf(' --%s', $key);
            } else {
                $command .= sprintf(' --%s "%s"', $key, $value);
            }
        }

        return $command;
    }

    /**
     * @return array<int, null|int|string>
     */
    public function process(string $command, bool $isQuiet): array
    {
        $proc = popen("{$command} 2>&1 ; echo Exit status: $?", 'r');

        if (false === $proc) {
            throw new ScriptException(sprintf('Error while executing command: %s', $command));
        }

        $completeOutput = '';

        while (!feof($proc)) {
            $liveOutput = fread($proc, 4096);
            $completeOutput = $completeOutput.$liveOutput;

            if (!$isQuiet) {
                echo "{$liveOutput}";
            }

            @flush();
        }

        pclose($proc);

        return $this->processResult($completeOutput);
    }

    /**
     * @return array<int, null|int|string>
     */
    public function processResult(string $completeOutput): array
    {
        // get exit status
        preg_match('/[0-9]+$/', $completeOutput, $matches);

        // return exit status and intended output
        return array_key_exists(0, $matches) ? [
            intval($matches[0]),
            preg_replace('/[\r\n]$/', '', str_replace(sprintf('Exit status: %s', $matches[0]), '', $completeOutput)),
        ] : [99, $completeOutput];
    }
}
