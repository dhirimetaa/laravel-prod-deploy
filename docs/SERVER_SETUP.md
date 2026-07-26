# Server Setup & Permissions

One-time (or after a fresh deploy) checklist for shared hosting / cPanel. Run these on the **server** via SSH, or use File Manager when SSH is limited.

Replace paths with your app root (`PROD_REMOTE_PATH`).

---

## 1. Storage directories (SSH)

Laravel needs writable framework dirs. From your app root:

```bash
cd /path/to/your-app   # e.g. ~/public_html/arpos.rashydh.com

mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/logs
mkdir -p bootstrap/cache

chmod -R 775 storage bootstrap/cache
```

If the web server user is `www-data` (common on VPS / some hosts):

```bash
chown -R www-data:www-data storage bootstrap/cache
```

On **cPanel shared hosting**, the owner is usually your cPanel username — skip `chown` to `www-data` if that fails. Prefer:

```bash
chmod -R 775 storage bootstrap/cache
```

Or via this package (from your Windows machine):

```bash
vendor/bin/prod-deploy terminal
# then at remote>:
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache
exit
```

Also run once after first deploy:

```bash
php artisan storage:link
```

---

## 2. File permissions — `.htaccess` → **644**

Apache / LiteSpeed needs `.htaccess` readable but not world-writable.

### File Manager (cPanel)

1. Open **File Manager** and go to your document root (often `public/` under the app, or `public_html` if that is the docroot).
2. Right-click **`.htaccess`** → **Change Permissions** (or **File Permissions**).
3. Set permissions to **644**:
   - **User:** Read + Write  
   - **Group:** Read  
   - **World:** Read  

### SSH

```bash
# App public directory (Laravel docroot)
chmod 644 public/.htaccess

# If document root is public_html and .htaccess lives there:
chmod 644 .htaccess
```

---

## 3. Folder permissions — website root → **755**

Directories should be executable so the web server can traverse them, but not world-writable.

### File Manager (cPanel)

1. Right-click your main website folder (often **`public_html`**, or the Laravel **`public`** folder if that is the document root).
2. Select **Change Permissions**.
3. Set to **755**:
   - **User:** Read + Write + Execute  
   - **Group:** Read + Execute  
   - **World:** Read + Execute  

### SSH

```bash
# Document root (example)
chmod 755 public_html
# or Laravel public folder:
chmod 755 public

# Typical recursive layout for the app (optional; be careful on shared hosts)
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod -R 775 storage bootstrap/cache
```

---

## 4. Quick reference

| Path / item              | Mode | Notes                                      |
|--------------------------|------|--------------------------------------------|
| Directories (general)    | 755  | App folders, `public_html`, `public/`      |
| Files (general)          | 644  | PHP, configs, assets                       |
| `.htaccess`              | 644  | In document root / `public/`               |
| `storage/`, `bootstrap/cache/` | 775 | Must be writable by PHP                    |

---

## 5. After fixing permissions

```bash
# From your machine
composer deploy:artisan -- config:clear
composer deploy:optimize
```

Or on the server:

```bash
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If you still see **500** or “permission denied” on logs/cache, re-check that `storage` and `bootstrap/cache` are **775** and owned by the account that runs PHP.
