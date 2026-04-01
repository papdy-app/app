<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\Console\Command\Command;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class CommandException extends \RuntimeException
{
    public function __construct(string $message = '', int $code = Command::FAILURE, ?\Throwable $previous = null)
    {
        parent::__construct(
            $message,
            $code,
            $previous
        );
    }
}
