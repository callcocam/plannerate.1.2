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
use Callcocam\Plannerate\Services\Engine\CategoryHierarchyService;
use Callcocam\Plannerate\Services\Engine\ABCHierarchicalService;
use Callcocam\Plannerate\Services\Engine\FacingCalculatorService;
use Callcocam\Plannerate\Services\Engine\HierarchicalDistributionService;

class PlannerateServiceProvider extends PackageServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        parent::register();

        // Registrar os services de distribuição hierárquica
        $this->app->singleton(CategoryHierarchyService::class, function ($app) {
            return new CategoryHierarchyService();
        });

        $this->app->singleton(ABCHierarchicalService::class, function ($app) {
            return new ABCHierarchicalService(
                $app->make(\Callcocam\Plannerate\Services\Analysis\ABCAnalysisService::class),
                $app->make(CategoryHierarchyService::class)
            );
        });

        $this->app->singleton(FacingCalculatorService::class, function ($app) {
            return new FacingCalculatorService();
        });

        $this->app->singleton(HierarchicalDistributionService::class, function ($app) {
            return new HierarchicalDistributionService(
                $app->make(CategoryHierarchyService::class),
                $app->make(ABCHierarchicalService::class),
                $app->make(FacingCalculatorService::class),
                $app->make(\Callcocam\Plannerate\Services\Analysis\TargetStockAnalysisService::class)
            );
        });
    }

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
                'create_layers_table'
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
}
