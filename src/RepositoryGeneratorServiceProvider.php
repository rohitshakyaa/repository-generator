<?php

namespace RohitShakyaa\RepositoryGenerator;

use Illuminate\Support\ServiceProvider;
use RohitShakyaa\RepositoryGenerator\Commands\MakeRepositoryCommand;
use RohitShakyaa\RepositoryGenerator\Commands\MakeServiceCommand;
use RohitShakyaa\RepositoryGenerator\Commands\RemoveRepositoryCommand;

class RepositoryGeneratorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Config
        $this->publishes([
            __DIR__ . '/../config/repository-generator.php' => config_path('repository-generator.php'),
        ], 'repository-generator-config');

        // Stubs
        $this->publishes([
            __DIR__ . '/stubs' => resource_path('stubs/repository-generator'),
        ], 'repository-generator-stubs');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/repository-generator.php',
            'repository-generator'
        );

        $this->commands([
            MakeRepositoryCommand::class,
            MakeServiceCommand::class,
            RemoveRepositoryCommand::class,
        ]);
    }
}
