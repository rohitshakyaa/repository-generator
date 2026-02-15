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

        $basePathRel = trim(config('repository-generator.repositories.path', 'Repositories'), '/');
        $interfacesFolder = trim(config('repository-generator.repositories.interfaces_folder', 'Interfaces'), '/');

        $basePath = app_path($basePathRel);
        $interfacePath = $basePath . '/' . dirname($name) . '/' . $interfacesFolder . '/' . class_basename($name) . 'Interface.php';
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
        $providerPath = app_path(config('repository-generator.repositories.provider_path', 'Providers/RepositoryServiceProvider.php'));
        if (File::exists($providerPath)) {
            $providerContent = File::get($providerPath);
            $interfaceClass = $this->buildClassPath($name . "Interface", $interfacesFolder);
            $implementationClass = $this->buildClassPath($name);

            $bindingLine = "\$this->app->bind(\\$interfaceClass::class, \\$implementationClass::class);";

            if (str_contains($providerContent, $bindingLine)) {
                $providerContent = str_replace("        $bindingLine\n", '', $providerContent);
                File::put($providerPath, $providerContent);
                $this->info("Unbound repository in " . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $providerPath));
            } else {
                $this->warn("Binding not found in " . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $providerPath));
            }
        } else {
            $this->warn("Repository service provider not found: " . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $providerPath));
        }

        return 0;
    }

    protected function buildClassPath($name, $prefix = '')
    {
        $basePathRel = trim(config('repository-generator.repositories.path', 'Repositories'), '/');
        $configuredNamespace = config('repository-generator.repositories.namespace');
        $namespace = $configuredNamespace
            ? (trim($configuredNamespace, '\\') . '\\')
            : ('App\\' . str_replace('/', '\\', $basePathRel) . '\\');

        $path = $prefix
            ? dirname($name) . '\\' . $prefix . '\\' . class_basename($name)
            : $name;

        return $namespace . str_replace('/', '\\', $path);
    }
}
