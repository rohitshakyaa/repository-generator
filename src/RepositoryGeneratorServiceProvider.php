<?php

namespace RohitShakyaa\RepositoryGenerator;

use Illuminate\Support\ServiceProvider;
use RohitShakyaa\RepositoryGenerator\Commands\MakeRepositoryCommand;
use RohitShakyaa\RepositoryGenerator\Commands\RemoveRepositoryCommand;

class RepositoryGeneratorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/stubs' => resource_path('stubs/repository-generator'),
        ], 'repository-generator-stubs');
    }

    public function register()
    {
        $this->commands([
            MakeRepositoryCommand::class,
            RemoveRepositoryCommand::class,
        ]);
    }
}
