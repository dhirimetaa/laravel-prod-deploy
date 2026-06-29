# Package Overview

## Problem

Deploying Laravel from **Windows** to **shared hosting** (cPanel, limited SSH) is painful:

- No reliable `rsync` or native incremental sync on Windows
- Uploading thousands of `vendor/` files over SFTP is slow
- Interactive SSH password prompts break automation
- Local dev artifacts (`public/hot`, `.env`, logs) must never reach production
- Frontend builds and Composer installs need a repeatable staging step

## Solution

**laravel-prod-deploy** provides a CLI that:

1. **Builds** a `prodfiles/` directory — a production-ready copy of your app with optimized Composer dependencies
2. **Pushes** changed files incrementally via SFTP (MD5 manifest)
3. **Uploads vendor** as a single zip for first deploy or bulk updates
4. **Runs remote artisan** over SSH using phpseclib (password or key from `deploy/deploy.env`)

## Design goals

| Goal | How |
|------|-----|
| Windows-friendly | PHP + phpseclib; no WSL/rsync required |
| Shared hosting | SFTP upload to `PROD_REMOTE_PATH`; cPanel doc root → `public/` |
| Fast rebuilds | Reuse `prodfiles/vendor/` when `composer.lock` unchanged |
| Safe pushes | Separate app vs vendor commands; push excludes for `.env`, logs, cache |
| Hotfixes | `push:target` for single files/folders without full rebuild |

## Default toolchain

- **Frontend:** `bun run build` (override in `deploy/config.php`)
- **Composer:** `composer install --no-dev --no-scripts --optimize-autoloader` inside prodfiles
- **Auth:** `PROD_SSH_PASSWORD` or `PROD_SSH_KEY` in gitignored `deploy/deploy.env`

## What this package is not

- Not a CI/CD platform — run commands locally or wire into your own pipeline
- Not a zero-downtime blue/green deployer — suited to single-server shared hosting
- Not a Composer plugin — add script aliases manually from `stubs/composer-scripts.json`

## Package vs project files

| Location | Purpose |
|----------|---------|
| Package `config/` | Default exclude lists (copied on `init`) |
| Package `stubs/` | Templates for `deploy.env`, `config.php` |
| Project `deploy/` | Your SSH credentials, overrides, manifests |
| Project `prodfiles/` | Gitignored build output (upload source) |
