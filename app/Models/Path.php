<?php

declare(strict_types=1);

namespace App\Models;

use FeWeDev\Base\Files;
use FeWeDev\Base\Variables;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Path
{
    public function __construct(protected Files $files, protected Variables $variables) {}

    public function getBasePath(): string
    {
        $basePath = base_path();

        if (str_contains($basePath, 'phar://')) {
            $phar = new \Phar($basePath);

            $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);

            $basePath = implode(DIRECTORY_SEPARATOR, [
                $tempDir,
                'papdy',
                app()->version(),
            ]);

            $scriptsPath = sprintf('%s%s%s', $basePath, DIRECTORY_SEPARATOR, 'scripts');

            if (!file_exists($scriptsPath)) {
                $this->files->createDirectory($basePath, 0755);

                $phar->extractTo($basePath);

                $files = scandir($basePath);

                foreach ($files as $file) {
                    $filePath = sprintf('%s%s%s', $basePath, DIRECTORY_SEPARATOR, $file);

                    if (is_file($filePath)) {
                        unlink($filePath);
                    } elseif (is_dir($filePath) && !in_array($file, ['.', '..', 'scripts', 'storage'])) {
                        $this->files->removeDirectory($filePath);
                    }
                }

                $directory = new \RecursiveDirectoryIterator($scriptsPath);
                $iterator = new \RecursiveIteratorIterator($directory);
                $regex = new \RegexIterator($iterator, '/^.+\.sh$/i', \RegexIterator::GET_MATCH);

                foreach ($regex as $file) {
                    if (is_array($file)) {
                        $file = reset($file);
                    }

                    chmod($this->variables->stringValue($file), 0755);
                }
            }
        }

        return $basePath;
    }
}
