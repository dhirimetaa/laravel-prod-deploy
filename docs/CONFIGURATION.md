# Configuration

## deploy/deploy.env

Gitignored. Required keys:

| Key | Description |
|-----|-------------|
| `PROD_SSH_HOST` | Server hostname |
| `PROD_SSH_USER` | SSH/SFTP username |
| `PROD_SSH_PORT` | SSH port (usually 22) |
| `PROD_REMOTE_PATH` | Absolute path to app root on server |

Optional auth (one required):

| Key | Description |
|-----|-------------|
| `PROD_SSH_PASSWORD` | Password auth |
| `PROD_SSH_KEY` | Path to private key (Windows paths OK) |

Copy from `stubs/deploy.env.example` via `prod-deploy init`.

---

## deploy/config.php

Optional PHP file returning an array merged over package defaults:

```php
<?php

return [
    'frontend_build' => ['bun', 'run', 'build'],
    'copy_roots' => ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'storage'],
    'copy_files' => ['artisan', 'composer.json', 'composer.lock'],
    'verify_after_build' => ['vendor/autoload.php', 'public/build/manifest.json'],
    'prodfiles_dir' => 'prodfiles',
    'config_dir' => 'deploy',
];
```

### Keys

| Key | Default | Description |
|-----|---------|-------------|
| `prodfiles_dir` | `prodfiles` | Staging directory relative to project root |
| `config_dir` | `deploy` | Deploy config directory |
| `frontend_build` | `['bun', 'run', 'build']` | Command array for asset build |
| `copy_roots` | Laravel app dirs | Directories copied recursively |
| `copy_files` | artisan, composer files | Individual root files |
| `verify_after_build` | autoload + vite manifest | Fail build if missing |

Use npm instead of bun:

```php
'frontend_build' => ['npm', 'run', 'build'],
```

---

## Exclude files

### deploy/exclude-build.txt

Patterns for files **not copied** into prodfiles. Copied from package on `init`; customize per project.

Syntax:

- Lines starting with `#` are comments
- `/path/` — directory prefix
- `/path/*.php` — glob patterns
- `*.md` — simple globs

### deploy/exclude-push.txt

Patterns **never uploaded** even if present in prodfiles. Protects live server state:

- `.env`
- `/storage/logs/**`
- `/bootstrap/cache/**`
- User uploads under `/storage/app/public/**`

Project file overrides package default at `config/exclude-push.txt`.

---

## Manifests (automatic)

| File | Purpose |
|------|---------|
| `deploy/.build-manifest.json` | Composer lock hash for vendor reuse |
| `deploy/.push-manifest.json` | MD5 map for incremental SFTP |

Both gitignored. Delete or use `--full` on push commands to force full upload.

---

## deploy/vendor.zip

Created by `vendor:zip` / `vendor:zip-push`. Gitignored. Temporary upload artifact.
