<?php

declare(strict_types=1);

namespace App\Models\Type;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
interface Type
{
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
    ): string;

    public function download(
        OutputInterface $output,
        string $serverName,
        string $serverFileName,
        string $localFileName,
        bool $isQuiet
    ): void;

    public function upload(
        OutputInterface $output,
        string $serverName,
        string $localFileName,
        string $serverFileName,
        bool $isQuiet
    ): void;

    public function delete(
        OutputInterface $output,
        string $serverName,
        string $serverFileName,
        bool $isQuiet
    ): void;
}
