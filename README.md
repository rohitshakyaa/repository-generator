
# Laravel Repository Generator

This Laravel package adds Artisan commands that:

- Creates a Repository class and its Interface
- Supports nested paths like `Config.RoleRepository`
- Automatically binds Interface to Implementation in `RepositoryServiceProvider`
- (Optional) Creates a Service class (with optional interface) and binds it in `ServiceServiceProvider`

## Installation

```bash
composer require rohitshakyaa/repository-generator
```

If Laravel doesn't auto-discover it, register manually in `config/app.php`:

```php
'providers' => [
    RohitShakyaa\RepositoryGenerator\RepositoryGeneratorServiceProvider::class,
]
```

if Laravel >= 11.*, in `bootstrap/providers.php`
```php
return [
    RohitShakyaa\RepositoryGenerator\RepositoryGeneratorServiceProvider::class,
]
```

## Usage

```bash
php artisan make:repository UserRepository
php artisan make:repository Config.RoleRepository
```

By default this will generate:

```
app/Repositories/Config/RoleRepository.php
app/Repositories/Config/Interfaces/RoleRepositoryInterface.php
```

And it will auto-bind them in:

```
app/Providers/RepositoryServiceProvider.php
```

### Optional: also generate a Service

```bash
php artisan make:repository Config.RoleRepository --service
```

Generate a service + interface + binding:

```bash
php artisan make:repository Config.RoleRepository --service --service-interface
```

### make:service

Create only a service class:

```bash
php artisan make:service Billing.InvoiceService
```

Create service + interface + auto-binding:

```bash
php artisan make:service Billing.InvoiceService --interface
```

And to remove repository
```bash
php artisan remove:repository UserRepository
php artisan remove:repository Config.RoleRepository
```

This will remove the files and the binding from `RepositoryServiceProvider`


### Publishing Stubs

To customize the stub files, publish them to your app:

```bash
php artisan vendor:publish --tag=repository-generator-stubs
```

### Publishing Config

```bash
php artisan vendor:publish --tag=repository-generator-config
```

#### Config options

You can change the base folders (relative to `app/`) and namespaces:

- `repositories.path` (default: `Repositories`)
- `services.path` (default: `Services`)

If you want the old v1.x behavior, set:

```php
// config/repository-generator.php
'repositories' => [
    'path' => 'Http/Repositories',
],
```
