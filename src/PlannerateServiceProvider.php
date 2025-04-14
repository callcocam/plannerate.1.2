<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Callcocam\Plannerate\Commands\PlannerateCommand;
use Spatie\LaravelPackageTools\Commands\InstallCommand;

class PlannerateServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('plannerate')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoutes('web', 'api')

            ->hasMigrations(
                'create_planograms_table',
                'create_gondolas_table',
                'create_sections_table',
                'create_shelves_table',
                'create_segments_table',
                'create_layers_table'
            )
            ->hasCommand(PlannerateCommand::class)
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishAssets()
                    ->publishMigrations()
                    ->publish('plannerate:translations')
                    ->askToRunMigrations()
                    ->copyAndRegisterServiceProviderInApp()
                    ->askToStarRepoOnGitHub('callcocam/plannerate');
            });
    }
}
