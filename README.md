# Laravel Prod Deploy

Build a production-ready `prodfiles/` staging directory and deploy Laravel applications to shared hosting via SFTP — designed for **Windows** developers on **cPanel** hosts with SSH/SFTP access.

## Quick start

```bash
composer require --dev dhirimetaa/laravel-prod-deploy
# Allow plugin when prompted, then:
vendor/bin/prod-deploy init
# Edit deploy/deploy.env with SSH credentials
composer deploy:build
composer deploy:vendor-zip-push   # first deploy: extract vendor.zip on server
composer deploy:push
composer deploy:migrate
composer deploy:optimize
```

The Composer plugin registers `deploy:*` scripts automatically on install (enable the plugin when Composer asks).

## Local development (path repository)

In your Laravel project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-prod-deploy",
            "options": { "symlink": true }
        }
    ],
    "require-dev": {
        "dhirimetaa/laravel-prod-deploy": "@dev"
    }
}
```

## Composer script aliases

Registered **automatically** by the Composer plugin on `composer require` / `composer update`. Enable the plugin when prompted:

```json
"config": {
    "allow-plugins": {
        "dhirimetaa/laravel-prod-deploy": true
    }
}
```

Reference copy (manual fallback): `stubs/composer-scripts.json`

## Documentation

| Doc | Description |
|-----|-------------|
| [PACKAGE_OVERVIEW.md](docs/PACKAGE_OVERVIEW.md) | Goals, problem/solution, Windows/shared hosting |
| [INSTALL.md](docs/INSTALL.md) | Install, init, first deploy |
| [ARCHITECTURE.md](docs/ARCHITECTURE.md) | Build/push flow, manifests, prodfiles |
| [COMMANDS.md](docs/COMMANDS.md) | Every CLI command and flag |
| [CONFIGURATION.md](docs/CONFIGURATION.md) | deploy.env, config.php, excludes |
| [ADOPTING_IN_A_PROJECT.md](docs/ADOPTING_IN_A_PROJECT.md) | Migrating from local deploy/ scripts |

## Requirements

- PHP 8.2+
- [phpseclib](https://phpseclib.com/) (installed via Composer)
- Bun (default frontend build) or customize `frontend_build` in `deploy/config.php`

## Repository

https://github.com/dhirimetaa/laravel-prod-deploy

## License

MIT — see [LICENSE](LICENSE).
