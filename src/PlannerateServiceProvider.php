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
use Callcocam\Plannerate\Commands\InstallFrontendCommand;
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
            ->hasAssets()
            ->hasMigrations(
                'create_planograms_table',
                'create_gondolas_table',
                'create_sections_table',
                'create_shelves_table',
                'create_segments_table',
                'create_layers_table',
                'add_distributed_width_to_segments_and_layers'
            )
            ->hasCommands([
                PlannerateCommand::class,
                InstallFrontendCommand::class,
            ])
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

    public function boot()
    {
        parent::boot();
        
        // Publicar apenas a migration específica
        $this->publishes([
            __DIR__ . '/../database/migrations/add_distributed_width_to_segments_and_layers.php.stub' => database_path('migrations/' . date('Y_m_d_His', time()) . '_add_distributed_width_to_segments_and_layers.php'),
        ], 'plannerate-distributed-width-migration');
    }
}
