<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Banner
{
    private const ACCENT = "\033[38;5;37m";

    private const DIM = "\033[2m";

    private const RESET = "\033[0m";

    /**
     * The logo in half blocks, parts in braces are drawn in the accent color.
     */
    private const LINES = [
        '       {▄▄} ████████',
        '     {▄▀}   ▀▀▀▀▀▀▀▀                     {    █}',
        '     {█}               █▀▀▀▄  ▀▀▀▄ █▀▀▀▄ {▄▀▀▀█ █   █}',
        '▄██▄{▄█▄▄▄} ████████   █   █ ▄▀▀▀█ █   █ {█   █ █   █}',
        '▀██▀ {█}    ▀▀▀▀▀▀▀▀   █▄▄▄▀ ▀▄▄▄█ █▄▄▄▀ {▀▄▄▄█  ▀▀▀█}',
        '     {█}               █           █     {      ▄▄▄▄▀}',
        '      {▀▄▄} ████████',
        '          ▀▀▀▀▀▀▀▀   ',
    ];

    private const TAGLINE = 'build and deploy php applications to servers';

    private static bool $shown = false;

    /**
     * Writes the logo once per run, colored when the output is decorated.
     */
    public function write(OutputInterface $output, ?string $version = null): void
    {
        if (self::$shown) {
            return;
        }

        self::$shown = true;

        $decorated = $output->isDecorated();
        $accent = $decorated ? self::ACCENT : '';
        $dim = $decorated ? self::DIM : '';
        $reset = $decorated ? self::RESET : '';

        $tagline = self::TAGLINE;

        if (null !== $version && '' !== $version) {
            $tagline .= ' · '.$version;
        }

        $lines = array_map(
            static fn (string $line): string => str_replace(['{', '}'], [$accent, $reset], $line),
            self::LINES
        );

        $lines[count($lines) - 1] .= $dim.$tagline.$reset;

        $output->writeln(array_merge([''], $lines, ['']), OutputInterface::OUTPUT_RAW);
    }

    /**
     * Writes the logo only when the output goes to a terminal, so that logs and pipes stay free of it.
     */
    public function writeToTerminal(OutputInterface $output, ?string $version = null): void
    {
        $streamOutput = $output instanceof OutputStyle ? $output->getOutput() : $output;

        if ($streamOutput instanceof StreamOutput && stream_isatty($streamOutput->getStream())) {
            $this->write($output, $version);
        }
    }
}
