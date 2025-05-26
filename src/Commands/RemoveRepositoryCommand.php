<?php

namespace RohitShakyaa\RepositoryGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RemoveRepositoryCommand extends Command
{
    protected $signature = 'remove:repository {name}';
    protected $description = 'Remove a Repository and its interface, and unbind it from RepositoryServiceProvider';

    public function handle()
    {
        $rawName = $this->argument('name');
        $normalized = str_replace(['.', '\\'], '/', $rawName); // Normalize dot and slashes
        $name = trim($normalized, '/');

        $basePath = app_path('Http/Repositories');
        $interfacePath = $basePath . '/' . dirname($name) . '/Interfaces/' . class_basename($name) . 'Interface.php';
        $repositoryPath = $basePath . '/' . $name . '.php';

        // Delete Repository
        if (File::exists($repositoryPath)) {
            File::delete($repositoryPath);
            $this->info("Deleted: $repositoryPath");
        } else {
            $this->warn("Repository not found: $repositoryPath");
        }

        // Delete Interface
        if (File::exists($interfacePath)) {
            File::delete($interfacePath);
            $this->info("Deleted: $interfacePath");
        } else {
            $this->warn("Interface not found: $interfacePath");
        }

        // Unbind in RepositoryServiceProvider
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');
        if (File::exists($providerPath)) {
            $providerContent = File::get($providerPath);
            $interfaceClass = $this->buildClassPath($name . "Interface", 'Interfaces');
            $implementationClass = $this->buildClassPath($name);

            $bindingLine = "\$this->app->bind(\\$interfaceClass::class, \\$implementationClass::class);";

            if (str_contains($providerContent, $bindingLine)) {
                $providerContent = str_replace("        $bindingLine\n", '', $providerContent);
                File::put($providerPath, $providerContent);
                $this->info("Unbound repository in app/Providers/RepositoryServiceProvider.");
            } else {
                $this->warn("Binding not found in app/Providers/RepositoryServiceProvider.");
            }
        } else {
            $this->warn("RepositoryServiceProvider not found.");
        }

        return 0;
    }

    protected function buildClassPath($name, $prefix = '')
    {
        $namespace = 'App\\Http\\Repositories\\';
        $path = $prefix
            ? dirname($name) . '\\' . $prefix . '\\' . class_basename($name)
            : $name;

        return $namespace . str_replace('/', '\\', $path);
    }
}
