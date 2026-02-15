<?php

namespace RohitShakyaa\RepositoryGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeServiceCommand extends Command
{
    protected $signature = 'make:service
        {name : e.g. UserService or Billing.InvoiceService or User}
        {--path= : Base folder inside app/ (defaults to config repository-generator.services.path)}
        {--interface : Also generate an interface and bind it in the service provider}';

    protected $description = 'Create a new Service class (optionally with interface) and bind it in ServiceServiceProvider';

    public function handle(): int
    {
        $input = $this->argument('name');

        // Parse input like "Billing.InvoiceService" or "User"
        $parts = explode('.', $input);
        $rawName = array_pop($parts);
        $folderPath = implode('/', $parts);
        $namespacePath = implode('\\', $parts);

        // Normalize: "User" => "UserService"
        $className = Str::endsWith($rawName, 'Service') ? $rawName : ($rawName . 'Service');

        $basePathRel = trim($this->option('path') ?: config('repository-generator.services.path', 'Services'), '/');
        $interfacesFolder = trim(config('repository-generator.services.interfaces_folder', 'Interfaces'), '/');

        $baseServicePath = app_path($basePathRel);
        $servicePath = $folderPath ? $baseServicePath . '/' . $folderPath : $baseServicePath;
        $interfacePath = $servicePath . '/' . $interfacesFolder;

        File::ensureDirectoryExists($servicePath);

        // Namespaces
        $baseNamespace = $this->resolveBaseNamespace(
            config('repository-generator.services.namespace'),
            $basePathRel
        );
        $namespace = $baseNamespace . ($namespacePath ? "\\{$namespacePath}" : '');
        $interfaceNamespace = $namespace . "\\{$interfacesFolder}";

        // Files
        $serviceClassPath = "{$servicePath}/{$className}.php";
        $relativeServicePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $serviceClassPath);

        if (!File::exists($serviceClassPath)) {
            File::put(
                $serviceClassPath,
                $this->generateServiceClass($namespace, $className, $interfaceNamespace, (bool) $this->option('interface'))
            );
            $this->info("Service created: {$relativeServicePath}");
        } else {
            $this->warn("Service already exists: {$relativeServicePath}");
        }

        if (!$this->option('interface')) {
            return Command::SUCCESS;
        }

        File::ensureDirectoryExists($interfacePath);

        $interfaceClassPath = "{$interfacePath}/{$className}Interface.php";
        $relativeInterfacePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $interfaceClassPath);

        if (!File::exists($interfaceClassPath)) {
            File::put($interfaceClassPath, $this->generateServiceInterface($interfaceNamespace, $className));
            $this->info("Service interface created: {$relativeInterfacePath}");
        } else {
            $this->warn("Service interface already exists: {$relativeInterfacePath}");
        }

        $this->registerBindingInProvider(
            providerPath: app_path(config('repository-generator.services.provider_path', 'Providers/ServiceServiceProvider.php')),
            providerClass: config('repository-generator.services.provider_class', 'ServiceServiceProvider'),
            interfaceFqcn: "\\{$interfaceNamespace}\\{$className}Interface",
            implementationFqcn: "\\{$namespace}\\{$className}"
        );

        return Command::SUCCESS;
    }

    protected function resolveBaseNamespace(?string $configuredNamespace, string $basePathRel): string
    {
        if ($configuredNamespace) {
            return trim($configuredNamespace, '\\');
        }

        $segments = array_filter(explode('/', str_replace('\\', '/', $basePathRel)));
        return 'App' . ($segments ? ('\\' . implode('\\', $segments)) : '');
    }

    protected function getStubPath(string $stubName): string
    {
        $publishedStub = resource_path("stubs/repository-generator/{$stubName}");
        if (file_exists($publishedStub)) {
            return $publishedStub;
        }

        return __DIR__ . "/../stubs/{$stubName}";
    }

    protected function generateServiceClass(string $namespace, string $className, string $interfaceNamespace, bool $withInterface): string
    {
        $stubPath = $this->getStubPath($withInterface ? 'service-with-interface.stub' : 'service.stub');
        $stub = file_get_contents($stubPath);

        return str_replace(
            ['{{ namespace }}', '{{ className }}', '{{ interfaceNamespace }}'],
            [$namespace, $className, $interfaceNamespace],
            $stub
        );
    }

    protected function generateServiceInterface(string $namespace, string $className): string
    {
        $stubPath = $this->getStubPath('service-interface.stub');
        $stub = file_get_contents($stubPath);

        return str_replace(
            ['{{ namespace }}', '{{ className }}'],
            [$namespace, $className],
            $stub
        );
    }

    protected function registerBindingInProvider(
        string $providerPath,
        string $providerClass,
        string $interfaceFqcn,
        string $implementationFqcn
    ): void {
        // pass WITHOUT semicolon; helper adds it
        $bindingLine = "\$this->app->bind({$interfaceFqcn}::class, {$implementationFqcn}::class)";

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

        // Avoid duplicates
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

        // No register() found → add one after class opening
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
