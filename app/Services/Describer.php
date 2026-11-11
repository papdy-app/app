<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Console\Application;
use Illuminate\Contracts\Config\Repository;
use NunoMaduro\LaravelConsoleSummary\Contracts\DescriberContract;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shows the logo instead of the plain name and version above the command summary.
 *
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Describer extends \NunoMaduro\LaravelConsoleSummary\Describer
{
    public function __construct(Repository $config, protected Banner $banner)
    {
        parent::__construct($config);
    }

    protected function describeTitle(Application $application, OutputInterface $output): DescriberContract
    {
        $this->banner->write($output, $application->getVersion());

        return $this;
    }
}
