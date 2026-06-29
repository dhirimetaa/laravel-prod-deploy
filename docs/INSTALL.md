# Installation

## 1. Require the package

From Packagist (when published):

```bash
composer require --dev dhirimetaa/laravel-prod-deploy
```

### Local path repository (development)

If the package lives at `../laravel-prod-deploy` relative to your Laravel app:

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

Then run `composer update dhirimetaa/laravel-prod-deploy`.

## 2. Initialize deploy config

From your Laravel project root:

```bash
vendor/bin/prod-deploy init
```

This creates:

```
deploy/
├── deploy.env          # from stub — edit with your server details
├── config.php          # optional overrides (mostly commented)
├── exclude-build.txt
└── exclude-push.txt
```

Also appends gitignore entries for `prodfiles/`, secrets, and manifests.

Use `--force` to overwrite existing files.

## 3. Configure SSH

Edit `deploy/deploy.env`:

```env
PROD_SSH_HOST=yourdomain.com
PROD_SSH_USER=cpanel_username
PROD_SSH_PORT=22
PROD_REMOTE_PATH=/home/cpanel_username/your-app

PROD_SSH_PASSWORD=your_password
# or
PROD_SSH_KEY=C:/Users/You/.ssh/id_rsa
```

## 4. Optional: Composer script aliases

Merge `vendor/dhirimetaa/laravel-prod-deploy/stubs/composer-scripts.json` (or copy from the package repo) into your project's `composer.json` `scripts` section.

## 5. Server one-time setup

SSH into production:

```bash
mkdir -p ~/your-app && cd ~/your-app
cp .env.example .env
# Edit APP_ENV=production, APP_DEBUG=false, database credentials
php artisan key:generate
php artisan storage:link
```

Point cPanel document root to `PROD_REMOTE_PATH/public`.

## 6. First deploy

```bash
vendor/bin/prod-deploy build
vendor/bin/prod-deploy vendor:zip-push
```

On the server:

```bash
cd ~/your-app && unzip -o vendor.zip && rm vendor.zip
```

Then:

```bash
vendor/bin/prod-deploy push
vendor/bin/prod-deploy remote migrate --force
vendor/bin/prod-deploy remote config:cache
vendor/bin/prod-deploy remote route:cache
vendor/bin/prod-deploy remote view:cache
```

## 7. Routine deploys

After code changes:

```bash
vendor/bin/prod-deploy build
vendor/bin/prod-deploy push
```

If `composer.lock` changed:

```bash
vendor/bin/prod-deploy build
vendor/bin/prod-deploy vendor:push    # or vendor:zip-push for bulk
vendor/bin/prod-deploy push
```

## Publishing to Packagist (maintainers)

1. Push repository to GitHub
2. Register at [packagist.org](https://packagist.org)
3. Submit `dhirimetaa/laravel-prod-deploy`
4. Tag releases (`v1.0.0`) for stable installs

Consumers then use `composer require --dev dhirimetaa/laravel-prod-deploy` without a path repository.
