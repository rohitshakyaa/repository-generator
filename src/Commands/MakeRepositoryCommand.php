<?php

namespace RohitShakyaa\RepositoryGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeRepositoryCommand extends Command
{
    protected $signature = 'make:repository {name}';
    protected $description = 'Create a new Repository and Interface, optionally nested, and bind in RepositoryServiceProvider';

    public function handle()
    {
        $input = $this->argument('name');

        // Parse input like "Config.RoleRepository"
        $parts = explode('.', $input);
        $className = array_pop($parts);
        $folderPath = implode('/', $parts);
        $namespacePath = implode('\\', $parts);

        // Base paths
        $baseRepoPath = app_path('Http/Repositories');
        $repoPath = $folderPath ? $baseRepoPath . '/' . $folderPath : $baseRepoPath;
        $interfacePath = $repoPath . '/Interfaces';

        // Ensure directories exist
        File::ensureDirectoryExists($repoPath);
        File::ensureDirectoryExists($interfacePath);

        // File paths
        $repoClassPath = "$repoPath/{$className}.php";
        $interfaceClassPath = "$interfacePath/{$className}Interface.php";

        // Namespaces
        $namespace = 'App\\Http\\Repositories' . ($namespacePath ? "\\$namespacePath" : '');
        $interfaceNamespace = $namespace . '\\Interfaces';

        // Create Repository Class
        $relativeRepoPath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $repoClassPath);
        if (!File::exists($repoClassPath)) {
            File::put($repoClassPath, $this->generateRepositoryClass($namespace, $className, $interfaceNamespace));
            $this->info("Repository created: $relativeRepoPath");
        } else {
            $this->warn("Repository already exists: $relativeRepoPath");
        }

        // Create Interface
        $relativeInterfacePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $interfaceClassPath);
        if (!File::exists($interfaceClassPath)) {
            File::put($interfaceClassPath, $this->generateInterface($interfaceNamespace, $className));
            $this->info("Interface created: $relativeInterfacePath");
        } else {
            $this->warn("Interface already exists: $relativeInterfacePath");
        }

        // Register in RepositoryServiceProvider
        $this->registerInServiceProvider($interfaceNamespace, $className, $namespace);

        return Command::SUCCESS;
    }

    protected function getStubPath($stubName)
    {
        // Check if published stub exists in app resources first
        $publishedStub = resource_path("stubs/repository-generator/{$stubName}");

        if (file_exists($publishedStub)) {
            return $publishedStub;
        }

        // Fallback to package's stub
        return __DIR__ . "/../stubs/{$stubName}";
    }

    protected function generateRepositoryClass($namespace, $className, $interfaceNamespace)
    {
        $stubPath = $this->getStubPath('repository.stub');
        $repositoryStub = file_get_contents($stubPath);
        $repositoryStub = str_replace(
            ['{{ namespace }}', '{{ className }}', '{{ interfaceNamespace }}'],
            [$namespace, $className, $interfaceNamespace],
            $repositoryStub
        );

        return $repositoryStub;
    }

    protected function generateInterface($namespace, $className)
    {
        $stubPath = $this->getStubPath('interface.stub');
        $interfaceStub = file_get_contents($stubPath);
        $interfaceStub = str_replace(
            ['{{ namespace }}', '{{ className }}'],
            [$namespace, $className],
            $interfaceStub
        );

        return $interfaceStub;
    }

    protected function registerInServiceProvider($interfaceNamespace, $className, $implementationNamespace)
    {
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');
        $binding = "\$this->app->bind(\\{$interfaceNamespace}\\{$className}Interface::class, \\{$implementationNamespace}\\{$className}::class);";

        if (!File::exists($providerPath)) {
            // Create the provider if not exists
            $this->callSilent('make:provider', ['name' => 'RepositoryServiceProvider']);
            $this->info('RepositoryServiceProvider created.');
        }

        $content = File::get($providerPath);

        if (Str::contains($content, $binding)) {
            $this->warn("Binding already exists in app/Providers/RepositoryServiceProvider.");
            return;
        }

        // If register() exists
        if (preg_match('/public function register\s*\(\)\s*\{/', $content)) {
            // Inject inside existing register() method
            $content = preg_replace_callback('/public function register\s*\(\)\s*\{([\s\S]*?)\n\s*\}/', function ($matches) use ($binding) {
                if (Str::contains($matches[1], $binding)) {
                    return $matches[0]; // Already contains the binding
                }
                return "public function register()\n    {\n        " . trim($matches[1]) . "\n        {$binding}\n    }";
            }, $content);
        } else {
            // Add register() method before the last closing bracket
            $injected = <<<PHP

    public function register()
    {
        {$binding}
    }

PHP;
            $content = preg_replace('/}\s*$/', $injected . "\n}", $content);
        }

        File::put($providerPath, $content);
        $this->info("Binding registered in app/Providers/RepositoryServiceProvider.");
    }
}
