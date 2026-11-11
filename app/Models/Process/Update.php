<?php

declare(strict_types=1);

namespace App\Models\Process;

use App\Exceptions\CommandException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Replaces the running installation with the latest release from GitHub.
 *
 * Every release commits its artifacts under build/ and tags them, so the files are downloaded from the tag:
 * the Debian package for installations made with it, the platform binary for a plain binary and the Phar for a Phar.
 *
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Update
{
    public const REPOSITORY = 'papdy-app/app';

    private const DEBIAN_PATH = '/usr/bin/papdy';

    private const DEBIAN_LIST = '/var/lib/dpkg/info/papdy.list';

    public function execute(OutputInterface $output, string $currentVersion, bool $checkOnly): void
    {
        $installation = $this->getInstallation();

        $output->writeln(sprintf('Current version: %s', $currentVersion));
        $output->writeln(sprintf('Installation: %s (%s)', $installation['path'], $installation['type']));

        $latestVersion = $this->getLatestVersion();

        $output->writeln(sprintf('Latest version: %s', $latestVersion));

        if (!version_compare($latestVersion, $currentVersion, '>')) {
            $output->writeln('<info>Papdy is up to date</info>');

            return;
        }

        if ($checkOnly) {
            $output->writeln(sprintf('<comment>Update available, install it with: papdy update</comment>'));

            return;
        }

        if ('source' === $installation['type']) {
            throw new CommandException(
                'Papdy runs from a source checkout, please update it with git instead'
            );
        }

        if ('debian' === $installation['type']) {
            $this->updateDebian($output, $latestVersion);
        } else {
            $this->replaceFile($output, $installation['path'], $installation['artifact'], $latestVersion);
        }

        $output->writeln(sprintf('<info>Updated Papdy from %s to %s</info>', $currentVersion, $latestVersion));

        // the running code was loaded from the file that has just been replaced, any class that is loaded from it
        // now (e.g. while the framework terminates) fails, so the process has to end here
        exit(0);
    }

    /**
     * @return array{type: string, path: string, artifact: string}
     */
    protected function getInstallation(): array
    {
        $pharPath = \Phar::running(false);

        if ('' === $pharPath) {
            $script = $_SERVER['SCRIPT_FILENAME'] ?? null;

            return [
                'type' => 'source',
                'path' => is_string($script) ? (realpath($script) ?: $script) : base_path(),
                'artifact' => '',
            ];
        }

        $path = realpath($pharPath) ?: $pharPath;

        if (!is_file($path)) {
            throw new CommandException(sprintf('Could not find the running installation, found: %s', $path));
        }

        // a binary built with phpacker runs the Phar from within the executable, which is then the Phar path
        // (PHP_BINARY is empty there)
        if ('micro' === PHP_SAPI) {
            if (self::DEBIAN_PATH === $path && file_exists(self::DEBIAN_LIST)) {
                return ['type' => 'debian', 'path' => $path, 'artifact' => 'build/linux/papdy.deb'];
            }

            return ['type' => 'binary', 'path' => $path, 'artifact' => $this->getBinaryArtifact()];
        }

        return ['type' => 'phar', 'path' => $path, 'artifact' => 'build/papdy.phar'];
    }

    protected function getBinaryArtifact(): string
    {
        $machine = strtolower(php_uname('m'));
        $architecture = in_array($machine, ['arm64', 'aarch64'], true) ? 'arm' : 'x64';

        return match (PHP_OS_FAMILY) {
            'Linux' => sprintf('build/linux/linux-%s', $architecture),
            'Darwin' => sprintf('build/mac/mac-%s', $architecture),
            'Windows' => 'build/windows/windows-x64.exe',
            default => throw new CommandException(sprintf('Unsupported operating system: %s', PHP_OS_FAMILY)),
        };
    }

    protected function getLatestVersion(): string
    {
        $url = sprintf('https://api.github.com/repos/%s/releases/latest', self::REPOSITORY);

        $response = @file_get_contents($url, false, $this->getContext(['Accept: application/vnd.github+json']));

        if (false === $response) {
            throw new CommandException(sprintf('Could not read the latest release from: %s', $url));
        }

        $release = json_decode($response, true);
        $tagName = is_array($release) ? ($release['tag_name'] ?? null) : null;

        if (!is_string($tagName) || !preg_match('/^\d+\.\d+\.\d+$/', $tagName)) {
            throw new CommandException(sprintf('Could not find a version in the latest release from: %s', $url));
        }

        return $tagName;
    }

    protected function getArtifactUrl(string $artifact, string $version): string
    {
        return sprintf(
            'https://raw.githubusercontent.com/%s/refs/tags/%s/%s',
            self::REPOSITORY,
            $version,
            $artifact
        );
    }

    protected function download(OutputInterface $output, string $artifact, string $version, string $targetPath): void
    {
        $url = $this->getArtifactUrl($artifact, $version);

        $output->writeln(sprintf('Downloading: %s', $url));

        if (!@copy($url, $targetPath, $this->getContext())) {
            @unlink($targetPath);

            throw new CommandException(sprintf('Could not download: %s', $url));
        }
    }

    protected function updateDebian(OutputInterface $output, string $version): void
    {
        if (function_exists('posix_geteuid') ? 0 !== posix_geteuid() : !is_writable(dirname(self::DEBIAN_PATH))) {
            throw new CommandException('Papdy was installed with its Debian package, please run: sudo papdy update');
        }

        $packagePath = sprintf('%s/papdy-%s.deb', rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR), $version);

        $this->download($output, 'build/linux/papdy.deb', $version, $packagePath);

        $output->writeln('Installing the Debian package');

        exec(sprintf('dpkg -i %s 2>&1', escapeshellarg($packagePath)), $lines, $resultCode);

        @unlink($packagePath);

        if (0 !== $resultCode) {
            throw new CommandException(sprintf('Could not install the Debian package: %s', implode("\n", $lines)));
        }
    }

    protected function replaceFile(OutputInterface $output, string $path, string $artifact, string $version): void
    {
        $directory = dirname($path);

        if (!is_writable($directory) || (file_exists($path) && !is_writable($path))) {
            throw new CommandException(sprintf(
                'No permission to replace: %s, please run the update as a user that can write it (e.g. with sudo)',
                $path
            ));
        }

        // next to the target, so that it can be moved in place in one step
        $newPath = sprintf('%s/.%s.%s.new', $directory, basename($path), $version);

        $this->download($output, $artifact, $version, $newPath);

        $permissions = fileperms($path);

        chmod($newPath, false === $permissions ? 0o755 : ($permissions & 0o777) | 0o755);

        $this->verify($newPath, $version, $artifact);

        if ('Windows' === PHP_OS_FAMILY) {
            // the running executable cannot be overwritten, but it can be renamed
            $oldPath = sprintf('%s.old', $path);

            @unlink($oldPath);

            if (!rename($path, $oldPath)) {
                @unlink($newPath);

                throw new CommandException(sprintf('Could not move the current version away: %s', $path));
            }
        }

        if (!rename($newPath, $path)) {
            @unlink($newPath);

            throw new CommandException(sprintf('Could not replace: %s', $path));
        }
    }

    /**
     * Runs the downloaded file to make sure that it works and is the expected version before it replaces anything.
     */
    protected function verify(string $newPath, string $version, string $artifact): void
    {
        $command = str_ends_with($artifact, '.phar')
            ? sprintf('%s %s --version 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($newPath))
            : sprintf('%s --version 2>&1', escapeshellarg($newPath));

        exec($command, $lines, $resultCode);

        $reported = trim(implode("\n", $lines));

        if (0 !== $resultCode || !str_contains($reported, $version)) {
            @unlink($newPath);

            throw new CommandException(sprintf(
                'The downloaded version does not work as expected, reported: %s',
                '' === $reported ? sprintf('exit code %d', $resultCode) : $reported
            ));
        }
    }

    /**
     * @param array<int, string> $headers
     *
     * @return resource
     */
    protected function getContext(array $headers = [])
    {
        return stream_context_create([
            'http' => [
                'header' => array_merge(['User-Agent: papdy'], $headers),
                'follow_location' => 1,
                'timeout' => 30,
            ],
        ]);
    }
}
