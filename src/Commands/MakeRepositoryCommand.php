<?php

namespace RohitShakyaa\RepositoryGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeRepositoryCommand extends Command
{
    protected $signature = 'make:repository
        {name : e.g. UserRepository or Config.RoleRepository or User}
        {--path= : Base folder inside app/ (defaults to config repository-generator.repositories.path)}
        {--service : Also generate a Service class alongside the repository}
        {--service-interface : When used with --service, also generate a Service interface and bind it}
        {--service-path= : Base folder for services inside app/ (defaults to config repository-generator.services.path)}';

    protected $description = 'Create a new Repository and Interface, optionally nested, and bind in RepositoryServiceProvider';

    public function handle(): int
    {
        $input = $this->argument('name');

        // Parse input like "Config.RoleRepository" or "User"
        $parts = explode('.', $input);
        $rawName = array_pop($parts); // could be "User" or "UserRepository"
        $folderPath = implode('/', $parts);
        $namespacePath = implode('\\', $parts);

        // Normalize: "User" => "UserRepository"
        $repoClassName = Str::endsWith($rawName, 'Repository') ? $rawName : ($rawName . 'Repository');
        // Base name for service: "UserRepository" => "User"
        $baseName = Str::replaceLast('Repository', '', $repoClassName);

        // Base paths
        $basePathRel = trim($this->option('path') ?: config('repository-generator.repositories.path', 'Repositories'), '/');
        $interfacesFolder = trim(config('repository-generator.repositories.interfaces_folder', 'Interfaces'), '/');

        $baseRepoPath = app_path($basePathRel);
        $repoPath = $folderPath ? $baseRepoPath . '/' . $folderPath : $baseRepoPath;
        $interfacePath = $repoPath . '/' . $interfacesFolder;

        File::ensureDirectoryExists($repoPath);
        File::ensureDirectoryExists($interfacePath);

        // File paths
        $repoClassPath = "{$repoPath}/{$repoClassName}.php";
        $interfaceClassPath = "{$interfacePath}/{$repoClassName}Interface.php";

        // Namespaces
        $baseNamespace = $this->resolveBaseNamespace(
            config('repository-generator.repositories.namespace'),
            $basePathRel
        );

        $namespace = $baseNamespace . ($namespacePath ? "\\{$namespacePath}" : '');
        $interfaceNamespace = $namespace . "\\{$interfacesFolder}";

        // Create Repository
        $relativeRepoPath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $repoClassPath);
        if (!File::exists($repoClassPath)) {
            File::put(
                $repoClassPath,
                $this->generateRepositoryClass($namespace, $repoClassName, $interfaceNamespace)
            );
            $this->info("Repository created: {$relativeRepoPath}");
        } else {
            $this->warn("Repository already exists: {$relativeRepoPath}");
        }

        // Create Interface
        $relativeInterfacePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $interfaceClassPath);
        if (!File::exists($interfaceClassPath)) {
            File::put(
                $interfaceClassPath,
                $this->generateInterface($interfaceNamespace, $repoClassName)
            );
            $this->info("Interface created: {$relativeInterfacePath}");
        } else {
            $this->warn("Interface already exists: {$relativeInterfacePath}");
        }

        // Register binding in RepositoryServiceProvider
        $this->registerInServiceProvider(
            providerPath: app_path(config('repository-generator.repositories.provider_path', 'Providers/RepositoryServiceProvider.php')),
            providerClass: config('repository-generator.repositories.provider_class', 'RepositoryServiceProvider'),
            interfaceNamespace: $interfaceNamespace,
            className: $repoClassName,
            implementationNamespace: $namespace
        );

        // Optional: also create Service
        if ($this->option('service')) {
            // Keep same nesting, but service is always BaseName + "Service"
            $serviceArgName = ($parts ? (implode('.', $parts) . '.') : '') . $baseName . 'Service';

            $args = [
                'name' => $serviceArgName,
                '--interface' => (bool) $this->option('service-interface'),
            ];

            if ($this->option('service-path')) {
                $args['--path'] = $this->option('service-path');
            }

            $this->call('make:service', $args);
        }

        return Command::SUCCESS;
    }

    protected function getStubPath($stubName)
    {
        $publishedStub = resource_path("stubs/repository-generator/{$stubName}");
        if (file_exists($publishedStub)) {
            return $publishedStub;
        }

        return __DIR__ . "/../stubs/{$stubName}";
    }

    protected function generateRepositoryClass($namespace, $className, $interfaceNamespace)
    {
        $stubPath = $this->getStubPath('repository.stub');
        $repositoryStub = file_get_contents($stubPath);

        return str_replace(
            ['{{ namespace }}', '{{ className }}', '{{ interfaceNamespace }}'],
            [$namespace, $className, $interfaceNamespace],
            $repositoryStub
        );
    }

    protected function generateInterface($namespace, $className)
    {
        $stubPath = $this->getStubPath('interface.stub');
        $interfaceStub = file_get_contents($stubPath);

        return str_replace(
            ['{{ namespace }}', '{{ className }}'],
            [$namespace, $className],
            $interfaceStub
        );
    }

    protected function resolveBaseNamespace(?string $configuredNamespace, string $basePathRel): string
    {
        if ($configuredNamespace) {
            return trim($configuredNamespace, '\\');
        }

        $segments = array_filter(explode('/', str_replace('\\', '/', $basePathRel)));
        return 'App' . ($segments ? ('\\' . implode('\\', $segments)) : '');
    }

    protected function registerInServiceProvider(
        string $providerPath,
        string $providerClass,
        string $interfaceNamespace,
        string $className,
        string $implementationNamespace
    ): void {
        // pass WITHOUT semicolon; helper adds it
        $bindingLine = "\$this->app->bind(\\{$interfaceNamespace}\\{$className}Interface::class, \\{$implementationNamespace}\\{$className}::class)";

        if (!File::exists($providerPath)) {
            $this->callSilent('make:provider', ['name' => $providerClass]);
            $this->info("{$providerClass} created.");
        }

        $this->addBindingToProvider($providerPath, $bindingLine);

        $this->info("Binding registered in " . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $providerPath));
    }

    private function addBindingToProvider(string $providerPath, string $bindingLine): void
    {
        if (!File::exists($providerPath)) {
            throw new \RuntimeException("Provider file not found: {$providerPath}");
        }

        $content = File::get($providerPath);

        // Avoid duplicates (line already present)
        if (Str::contains($content, $bindingLine . ';') || Str::contains($content, $bindingLine)) {
            return;
        }

        $eol = Str::contains($content, "\r\n") ? "\r\n" : "\n";

        // Match register() with or without ": void"
        $pattern = '/(public\s+function\s+register\s*\(\s*\)\s*(?::\s*void\s*)?\s*\{)([\s\S]*?)\n(\s*)\}/m';

        if (preg_match($pattern, $content, $m)) {
            $header = $m[1];
            $body = $m[2];
            $closingIndent = $m[3];

            $insert = $eol . $closingIndent . '    ' . $bindingLine . ';';
            $newBody = rtrim($body) . $insert . $eol . $closingIndent;

            $replacement = $header . $newBody . '}';

            $content = preg_replace($pattern, $replacement, $content, 1);
            File::put($providerPath, $content);
            return;
        }

        // No register() found → insert a new one after class opening
        $classPattern = '/(class\s+\w+\s+extends\s+ServiceProvider\s*\{)/m';
        if (!preg_match($classPattern, $content)) {
            throw new \RuntimeException("Could not locate ServiceProvider class in: {$providerPath}");
        }

        $registerMethod =
            $eol . $eol .
            '    public function register()' . $eol .
            '    {' . $eol .
            '        ' . $bindingLine . ';' . $eol .
            '    }' . $eol;

        $content = preg_replace($classPattern, '$1' . $registerMethod, $content, 1);
        File::put($providerPath, $content);
    }
}
