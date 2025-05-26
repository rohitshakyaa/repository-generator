
# Laravel Repository Generator

This Laravel package adds a `make:repository` Artisan command that:

- Creates a Repository class and its Interface
- Supports nested paths like `Config.RoleRepository`
- Automatically binds Interface to Implementation in `RepositoryServiceProvider`

## Installation

```bash
composer require rohitshakyaa/repository-generator
````

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

This will generate:

```
app/Http/Repositories/Config/RoleRepository.php
app/Http/Repositories/Config/Interfaces/RoleRepositoryInterface.php
```

And it will auto-bind them in:

```
app/Providers/RepositoryServiceProvider.php
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
