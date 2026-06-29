# Adopting in an Existing Project

This guide covers migrating a Laravel app that already has local `deploy/` PHP scripts (like ArPOS) to the **laravel-prod-deploy** package.

## When to migrate

Migrate when you want:

- One maintained package instead of copied scripts per project
- Composer-managed updates via Packagist or path repo
- Consistent CLI across multiple Laravel apps

## Migration steps

### 1. Require the package

Add path repo or Packagist requirement (see [INSTALL.md](INSTALL.md)).

### 2. Compare existing config

Before removing local scripts, diff your project's:

- `deploy/deploy.env` — should work as-is (same variable names)
- `deploy/exclude-build.txt` / `exclude-push.txt` — merge any custom rules
- Custom build settings — move to `deploy/config.php`

### 3. Run init (carefully)

```bash
vendor/bin/prod-deploy init
```

If you already have `deploy/deploy.env`, init **skips** existing files. Use `--force` only if you want to reset from stubs.

### 4. Update Composer scripts

Replace local script entries:

```json
"deploy:build": "php deploy/build.php"
```

With:

```json
"deploy:build": "prod-deploy build"
```

Full snippet: C:\laragon\www\laravel-prod-deploy\stubs\composer-scripts.json.

### 5. Remove legacy scripts

After verifying build/push/remote work, delete:

```
deploy/bootstrap.php
deploy/shared.php
deploy/build.php
deploy/push.php
deploy/push-target.php
deploy/vendor-push.php
deploy/vendor-zip.php
deploy/vendor-zip-push.php
deploy/remote.php
```

**Keep:**

```
deploy/deploy.env
deploy/config.php          (if customized)
deploy/exclude-build.txt
deploy/exclude-push.txt
deploy/.push-manifest.json  (optional — can reset)
deploy/.build-manifest.json
```

### 6. Verify workflow

```bash
vendor/bin/prod-deploy build --dry-run   # N/A for build; use normal build
vendor/bin/prod-deploy push --dry-run
vendor/bin/prod-deploy remote migrate --force
```

## ArPOS note

ArPOS (`c:\laragon\www\arpos`) ships with local deploy scripts and is **not** migrated automatically. When ready:

```json
"repositories": [{ "type": "path", "url": "../laravel-prod-deploy", "options": { "symlink": true } }],
"require-dev": { "rashydhc/laravel-prod-deploy": "@dev" }
```

Then follow steps above manually. Existing manifests and `deploy.env` can be reused.

## Rollback

If needed, restore deploy PHP scripts from git history and revert Composer script entries. Manifests remain compatible — same JSON format.
