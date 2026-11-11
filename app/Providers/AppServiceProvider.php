<?php

namespace App\Providers;

use App\Models\Config;
use App\Models\Process\Parameter\Build;
use App\Models\Process\Parameter\Deploy;
use App\Services\Describer;
use FeWeDev\Base\Variables;
use Illuminate\Support\ServiceProvider;
use LaravelZero\Framework\Application;
use NunoMaduro\LaravelConsoleSummary\Contracts\DescriberContract;

/**
 * @author      Andreas Knollmann
 * @copyright   2014-2026 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // in boot, so that it replaces the binding of the console summary package regardless of the provider order
        $this->app->singleton(DescriberContract::class, Describer::class);
    }

    public function register(): void
    {
        $this->app->singleton(
            'process.parameters.build',
            function (Application $app) {
                return new Build(
                    $app->make(Variables::class),
                    $app->make(Config::class)
                );
            }
        );

        $this->app->singleton(
            'process.parameters.deploy',
            function (Application $app) {
                return new Deploy(
                    $app->make(Variables::class),
                    $app->make(Config::class)
                );
            }
        );
    }
}
