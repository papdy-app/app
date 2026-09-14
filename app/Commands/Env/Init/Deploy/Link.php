<?php

declare(strict_types=1);

namespace App\Commands\Env\Init\Deploy;

use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Link extends Base
{
    protected function getCommandName(): string
    {
        return 'env:init:deploy:link';
    }

    protected function getCommandDescription(): string
    {
        return 'Add link in deploy process';
    }

    protected function getCommandParameters(): array
    {
        return array_merge(
            $this->getBaseCommandParameters(),
            $this->getLinkCommandParameters()
        );
    }

    /**
     * @throws BindingResolutionException
     */
    protected function executeCommand(): int
    {
        [$serverName, $host, $id] = $this->getBaseOptions();

        $source = $this->getRequiredOption('source', 'No source specified!');
        $target = $this->getOption('target');

        $process = $this->app->make(\App\Models\Process\Env\Init\Deploy\Link::class);

        $process->execute($serverName, $host, $id, $source, $target);

        return self::SUCCESS;
    }

    /**
     * @return array<string>
     */
    private function getLinkCommandParameters(): array
    {
        return [
            $this->prepareInputOption('source', 'Source path of link'),
            $this->prepareInputOption('target', 'Target path of link, default: [source]'),
        ];
    }
}
