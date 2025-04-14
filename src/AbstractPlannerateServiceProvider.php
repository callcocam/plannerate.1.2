<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate;

use Spatie\LaravelPackageTools\Package;
use Callcocam\Plannerate\Plannerate as PlannerateApp;
use Illuminate\Support\ServiceProvider;


abstract class AbstractPlannerateServiceProvider extends ServiceProvider
{
     abstract public function plannerate(PlannerateApp $plannerate): PlannerateApp;


     /**
      * Register the service provider.
      * @return void
      */
     public function register()
     {
          $this->app->singleton(PlannerateApp::class, function ($app) {
               return $this->plannerate(PlannerateApp::make());
          });
     }
}
