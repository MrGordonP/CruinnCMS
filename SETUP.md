# CruinnCMS — Setup Guide

## Contents

1. [Requirements](#1-requirements)
2. [Install](#2-install)
3. [Database setup](#3-database-setup)
4. [Web server configuration](#4-web-server-configuration)
5. [Run the install wizard](#5-run-the-install-wizard)
6. [Provision your first instance](#6-provision-your-first-instance)
7. [Local development server](#7-local-development-server)
8. [Production deployment](#8-production-deployment)

---

## 1. Requirements

| Dependency | Minimum version |
|------------|----------------|
| PHP | 8.2 |
| MySQL | 8.0 |
| Nginx | any current | 
| Composer | 2.x |

PHP extensions required: `pdo_mysql`, `mbstring`, `fileinfo`, `json`, `openssl`

---

## 2. Install

```bash
git clone https://github.com/MrGordonP/CruinnCMS.git
cd CruinnCMS
composer install
```

Set directory permissions so the web server can write to:

```bash
chmod -R 775 public/storage public/uploads instance
chown -R www-data:www-data public/storage public/uploads instance
```

---

## 3. Database setup

CruinnCMS uses two databases:

- **Platform DB** — stores platform settings and the instances registry. Created once per install.
- **Instance DB** — one per site. Created when you provision an instance through the platform dashboard.

Create the platform database and a dedicated user:

```sql
CREATE DATABASE cruinncms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cruinn'@'localhost' IDENTIFIED BY 'your-password';
GRANT ALL PRIVILEGES ON cruinncms.* TO 'cruinn'@'localhost';
```

The schema is applied automatically by the install wizard. If you prefer to apply it manually:

```bash
mysql -u cruinn -p cruinncms < schema/platform.sql
```

Each instance database is created and provisioned through the platform dashboard (`/cms/`). The schema at `schema/instance_core.sql` is applied automatically at provisioning time.

---

## 4. Web server configuration

### Nginx

A ready-to-use config is at `config/nginx.conf`. Copy it to your sites directory and replace `yoursite.example` with your domain:

```bash
cp config/nginx.conf /etc/nginx/sites-available/yoursite.example
# Edit the file to set your domain and certificate paths
ln -s /etc/nginx/sites-available/yoursite.example /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

The key requirement is that all requests are routed to `public/index.php`:

```nginx
root /var/www/yoursite/public;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### Apache

Add a `.htaccess` in `public/`:

```apache
RewriteEngine On
RewriteCond %{ REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

### SSL

The included Nginx config assumes Let's Encrypt certificates managed by Certbot:

```bash
certbot --nginx -d yoursite.example -d www.yoursite.example
```

---

## 5. Run the install wizard

Visit `https://yoursite.example/cms/install` in your browser.

The wizard will:

1. Accept your platform database credentials
2. Create `config/CruinnCMS.php` with a bcrypt-hashed admin credential
3. Apply `schema/platform.sql` to the platform database
4. Redirect you to the platform login at `/cms/login`

> **Default credential** written by the wizard: username `platform`, password `platform-admin`.  
> **Change this immediately** after first login via `/cms/settings`.

`config/CruinnCMS.php` is gitignored — never commit it. Back it up securely outside the repo.

---

## 6. Provision your first instance

After logging in to the platform dashboard at `/cms/`:

1. Click **New Instance**
2. Enter a slug (e.g. `mysite`), display name, and the instance database credentials
3. The wizard creates `instance/mysite/config.php` and applies `schema/instance_core.sql`
4. Click **Activate** to set this as the live instance
5. Visit `/admin` to access the instance admin panel

The instance admin uses separate credentials stored in the instance database. The default user created at provisioning is `admin` / `admin` — change it immediately in **Admin → My Account**.

---

## 7. Local development server

PHP's built-in server works for development. From the repo root:

```bash
php -S localhost:8000 -t public router.php
```

`router.php` in the repo root handles static file passthrough so CSS/JS/images are served correctly alongside routed PHP requests.

---

## 8. Production deployment

### File permissions

```bash
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 775 public/storage public/uploads instance
```

### Environment variables (optional)

The active instance can be set via environment variable instead of `instance/.active`:

```bash
export CRUINN_INSTANCE=mysite
```

This is useful in containerised deployments where writing to the filesystem is not desirable.

### Keeping credentials out of the repo

These files are gitignored and must **never** be committed:

| File | Contains |
|------|---------|
| `config/CruinnCMS.php` | Platform DB credentials + bcrypt hash |
| `config/config.local.php` | Local/VPS config overrides |
| `instance/*/config.php` | Per-instance DB credentials |
| `instance/.active` | Active instance pointer |

Back them up outside the repository (e.g. server-side only, not in any git remote).

### Updating

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
```

If a release includes schema changes, migration files will be noted in the release notes. Apply them manually:

```bash
mysql -u cruinn -p cruinncms < schema/migration-xyz.sql
```
