# Changelog

All notable changes to this project will be documented in this file.

The format is based on *Keep a Changelog* and this project adheres to *Semantic Versioning*.

## [2.0.0] - 2026-02-15

### Added
- Config file (`config/repository-generator.php`) to customize repository/service base paths, namespaces, and provider locations.
- `php artisan make:service` command.
- Service stubs (`service.stub`, `service-interface.stub`, `service-with-interface.stub`).
- Optional service generation from `make:repository` via `--service` and `--service-interface`.
- New publish tag: `repository-generator-config`.

### Changed
- **Default repository path** is now `app/Repositories` (previously `app/Http/Repositories`).
  - To keep v1.x behavior, set `repositories.path` to `Http/Repositories` in the config.
- Repository removal and binding removal now respect the configured repository path/provider path.

## [1.0.0] - 2026-02-15

### Added
- Initial release: `make:repository` and `remove:repository` with stub publishing and auto-binding in `RepositoryServiceProvider`.
