# Commands

Run from your Laravel project root (where `composer.json` has `"type": "project"`).

Global help:

```bash
vendor/bin/prod-deploy --help
```

---

## `build`

Build frontend assets and populate `prodfiles/`.

```bash
prod-deploy build [--force-composer]
```

| Flag | Description |
|------|-------------|
| `--force-composer` | Delete and reinstall vendor even if `composer.lock` unchanged |

**Steps:** frontend build → copy app files → storage skeleton → composer install (if needed) → verify.

---

## `push`

Incremental SFTP upload of **app files only** (excludes `vendor/`).

```bash
prod-deploy push [--dry-run] [--full]
```

| Flag | Description |
|------|-------------|
| `--dry-run` | List files that would upload |
| `--full` | Ignore push manifest; upload all app files in prodfiles |

Warns if vendor changed — run `vendor:push` or `vendor:zip-push` separately.

---

## `push:target`

Upload specific paths (hotfix). Copies from project root into prodfiles if needed.

```bash
prod-deploy push:target [--dry-run] [--allow-vendor] -- <path> [path2 ...]
```

| Flag | Description |
|------|-------------|
| `--dry-run` | List files only |
| `--allow-vendor` | Allow paths under `vendor/` |

Examples:

```bash
prod-deploy push:target -- app/Livewire/Dashboard.php
prod-deploy push:target -- bootstrap/cache/config.php
prod-deploy push:target --allow-vendor -- vendor/laravel/framework/src/...
```

Explicit targets bypass push exclude rules.

---

## `vendor:push`

Incremental upload of changed files under `prodfiles/vendor/`.

```bash
prod-deploy vendor:push [--dry-run] [--full]
```

Best for small dependency updates after `build`.

---

## `vendor:zip`

Create `deploy/vendor.zip` locally from `prodfiles/vendor/`.

```bash
prod-deploy vendor:zip
```

Does not upload. Use before manual FileZilla upload or `vendor:zip-push`.

---

## `vendor:zip-push`

Build vendor zip and upload as single file to server root.

```bash
prod-deploy vendor:zip-push [--dry-run] [--full]
```

After upload, extract on server:

```bash
cd $PROD_REMOTE_PATH && unzip -o vendor.zip && rm vendor.zip
```

Recommended for first deploy and large vendor refreshes.

---

## `artisan`

Run artisan on production via SSH (phpseclib).

```bash
prod-deploy artisan <command> [options...]
```

Examples:

```bash
prod-deploy artisan migrate --force
prod-deploy artisan config:cache
prod-deploy artisan queue:restart
prod-deploy artisan tinker --execute="echo 1;"
```

Arguments are shell-escaped before remote execution.

---

## `migrate`

Shortcut for `artisan migrate --force` on production.

```bash
prod-deploy migrate [-- extra-artisan-args...]
```

Examples:

```bash
prod-deploy migrate
prod-deploy migrate --seed
```

Always includes `--force` (required for production migrations).

---

## `optimize`

Run Laravel production cache commands on the server.

```bash
prod-deploy optimize
```

Runs remotely, in order:

1. `config:cache`
2. `route:cache`
3. `view:cache`

---

## `remote`

Alias for `artisan` (kept for backward compatibility).

```bash
prod-deploy remote <artisan-args...>
```

---

## `init`

Scaffold `deploy/` in the consuming project.

```bash
prod-deploy init [--force]
```

| Flag | Description |
|------|-------------|
| `--force` | Overwrite existing deploy files |

Creates `deploy.env`, `config.php`, exclude files, and updates `.gitignore`.

---

## Exit codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Error (message on stderr via `Output::fail`) |
