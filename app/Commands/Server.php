<?php

declare(strict_types=1);

namespace App\Commands;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Server extends Base
{
    /**
     * @return array<int, string>
     */
    protected function getCommandParameters(): array
    {
        return array_merge_recursive(
            $this->getServerCommandParameters(),
            [$this->prepareInputOption('server', 'Execute on server', true)]
        );
    }

    /**
     * @return array<int, string>
     */
    abstract protected function getServerCommandParameters(): array;

    protected function executeCommand(): int
    {
        $servers = $this->getOptionList('server');

        return $this->executeServerCommand($servers);
    }

    /**
     * @param array<int, string> $servers
     */
    abstract protected function executeServerCommand(array $servers): int;
}
