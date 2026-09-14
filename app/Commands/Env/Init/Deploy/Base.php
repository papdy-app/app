<?php

declare(strict_types=1);

namespace App\Commands\Env\Init\Deploy;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Base extends \App\Commands\Base
{
    /**
     * @return array<string>
     */
    protected function getBaseCommandParameters(): array
    {
        return [
            $this->prepareInputOption('serverName', 'Name of the server to deploy on'),
            $this->prepareDefaultInputOption('host', 'localhost', 'Host name of the server to deploy on'),
            $this->prepareInputOption('id', 'Id of deploy, default: [serverName]_deploy'),
        ];
    }

    /**
     * @return array<null|string>
     */
    protected function getBaseOptions(): array
    {
        $serverName = $this->getOption('serverName');
        $host = $this->getOption('host');
        $id = $this->getOption('id');

        return [$serverName, $host, $id];
    }
}
