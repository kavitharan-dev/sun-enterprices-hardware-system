# Production deployment — SUN ENTERPRICES

Target: Ubuntu 22.04/24.04 + Nginx + PHP 8.2 + MySQL 8 on **https://sunenterprise.lk**.

Before starting, the domain's DNS **A record** must already point at the server's
IP address. `ping sunenterprise.lk` should return that IP, or the SSL step in
section 6 will fail.

> Not comfortable with the Linux command line? [Laravel Forge](https://forge.laravel.com)
> performs sections 1, 2, 6, 7 and 8 for you from a web interface. The
> application-specific parts (sections 3, 4, 5 and 9) still apply.

## 1. Server packages

```bash
sudo apt update
sudo apt install nginx php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-gd unzip git mysql-server curl
```

PHP-FPM, not XAMPP. `mysql-server` also provides `mysqldump`, which the backup
command in section 5 requires.

Node.js is needed to build the CSS and JavaScript:

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

## 2. Database

```bash
sudo mysql
```

```sql
CREATE DATABASE sun_enterprise CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sun_enterprise'@'127.0.0.1' IDENTIFIED BY 'use-a-strong-password';
GRANT ALL PRIVILEGES ON sun_enterprise.* TO 'sun_enterprise'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

Binding the user to `127.0.0.1` keeps the database unreachable from the internet.

## 3. Application

```bash
sudo mkdir -p /var/www/sunenterprise
sudo chown -R $USER:www-data /var/www/sunenterprise
cd /var/www/sunenterprise
git clone https://github.com/kavitharan-dev/sun-enterprices-hardware-system.git .
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

The repository is private, so `git clone` will ask for GitHub credentials. Use a
[personal access token](https://github.com/settings/tokens) with `repo` scope as
the password, or add a deploy key to the repository.

`npm run build` is not optional. Without it no styling or buttons load and the
application looks broken.

## 4. Production `.env` (required)

```env
APP_NAME="SUN ENTERPRICES"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sunenterprise.lk
APP_FORCE_HTTPS=true
APP_TIMEZONE=Asia/Colombo

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sun_enterprise
DB_USERNAME=sun_enterprise
DB_PASSWORD=use-a-strong-password

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.sunenterprise.lk

QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS="noreply@sunenterprise.lk"
MAIL_FROM_NAME="SUN ENTERPRICES"

SMS_ENABLED=true
SMS_PROVIDER=textlk
SMS_API_KEY=your-textlk-token
SMS_SENDER_ID=SunEnt
```

`APP_DEBUG=false` matters most: with debug on, any error page exposes the
database password and file paths to whoever triggered it.

Never commit real credentials. Keep a copy of the finished `.env` somewhere safe
(a password manager) — it is deliberately excluded from the repository, so it
exists only on this server.

**After `php artisan config:cache`, editing `.env` has no effect until you run
`php artisan config:clear`.** This is the single most common source of "I changed
it but nothing happened".

## 5. Off-site backups (required for the no-data-loss guarantee)

Backups run **hourly** and are gzipped. A backup left on the same server is lost
with that server, so configure a remote bucket. Any S3-compatible storage works;
Backblaze B2 is the cheapest option for this size of data.

```env
BACKUP_OFFSITE_DISK=s3
BACKUP_OFFSITE_PATH=database-backups
BACKUP_KEEP_LOCAL_DAYS=2
BACKUP_KEEP_OFFSITE_DAYS=30
BACKUP_COMPRESS=true

AWS_ACCESS_KEY_ID=your-storage-key
AWS_SECRET_ACCESS_KEY=your-storage-secret
AWS_BUCKET=sunenterprise-backups
AWS_DEFAULT_REGION=us-east-005
AWS_ENDPOINT=https://s3.us-east-005.backblazeb2.com
```

Verify it end to end before go-live:

```bash
php artisan app:backup-database
```

Success prints `Copied off-site to [s3] database-backups/...`. If it instead
prints `No off-site copy`, `BACKUP_OFFSITE_DISK` is not set and **you have no
disaster protection**. The command exits non-zero when a configured upload
fails, so a failing scheduler run is a real alert worth acting on.

### Restoring

```bash
gunzip < backup-mysql-2026-08-19_140000.sql.gz | \
  mysql -u sun_enterprise -p sun_enterprise
```

Do a test restore into a scratch database once before go-live. An untested backup
is a hope, not a backup.

## 6. Migrate and seed

```bash
php artisan migrate --force
```

Then seed **only** these four, in this order:

```bash
php artisan db:seed --force --class=RolePermissionSeeder
php artisan db:seed --force --class=SettingsSeeder
php artisan db:seed --force --class=SunEnterpriseProductSeeder
php artisan db:seed --force --class=SunEnterprisePriceSeeder
```

This creates the roles and login accounts, company settings, all 200 catalog
products, and their prices.

> **Do not run plain `php artisan db:seed --force`.** The full `DatabaseSeeder`
> also runs `StoreCatalogSeeder` and `ConstructionSeeder`, which insert demo
> data — a fake project ("Kumar Residence", Nugegoda), fake workers ("Sunil
> Perera", "Ravi Fernando"), a demo customer and a demo material request. You
> would then have to find and delete all of it from the client's live system.

> **`RolePermissionSeeder` creates all four accounts with the password
> `password`, and re-running it resets them.** Change every password immediately
> after the first login, and never run that seeder again once the system is live.

Finish with:

```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 7. Nginx + SSL

The full config references certificate files that do not exist yet, so install
the HTTP-only config first:

```bash
sudo cp deploy/nginx/sunenterprise.lk.http.conf /etc/nginx/sites-available/sunenterprise.lk
sudo ln -s /etc/nginx/sites-available/sunenterprise.lk /etc/nginx/sites-enabled/sunenterprise.lk
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Confirm `http://sunenterprise.lk` loads, then obtain the certificate:

```bash
sudo certbot --nginx -d sunenterprise.lk -d www.sunenterprise.lk
```

Now the certificate exists, so switch to the full config with security headers
and the HTTP-to-HTTPS redirect:

```bash
sudo cp deploy/nginx/sunenterprise.lk.conf /etc/nginx/sites-available/sunenterprise.lk
sudo nginx -t && sudo systemctl reload nginx
```

Certbot installs its own renewal timer. Check it with
`sudo certbot renew --dry-run`.

## 8. Queue worker (SMS + email)

Create `/etc/systemd/system/sunenterprise-queue.service`:

```ini
[Unit]
Description=SUN ENTERPRICES queue worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/sunenterprise/artisan queue:work database --sleep=1 --tries=3 --timeout=120
WorkingDirectory=/var/www/sunenterprise

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now sunenterprise-queue
```

Restart it after every deployment, or it keeps running the old code.

## 9. Scheduler

```bash
sudo crontab -e -u www-data
```

```
* * * * * cd /var/www/sunenterprise && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs:

- Database backup — **hourly** (section 5)
- Low-stock checks (08:00 Asia/Colombo)
- Failed SMS retry + delivery status (every 15 minutes)
- Overdue payment and project deadline alerts (08:30 Asia/Colombo)

Without this cron entry none of the above ever runs, and nothing reports an
error.

## 10. Permissions and firewall

```bash
sudo chown -R www-data:www-data /var/www/sunenterprise/storage /var/www/sunenterprise/bootstrap/cache
sudo chmod -R ug+rwx /var/www/sunenterprise/storage /var/www/sunenterprise/bootstrap/cache
```

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo apt install fail2ban && sudo systemctl enable --now fail2ban
```

## 11. Health check

`https://sunenterprise.lk/up`

## 12. Go-live checklist

- [ ] DNS A record resolves to the server before starting section 7
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://sunenterprise.lk`
- [ ] MySQL 8 database created; `php artisan migrate --force` (never `migrate:fresh`)
- [ ] Only the four seeders in section 6 were run — no demo project or workers present
- [ ] All four default passwords changed from `password`
- [ ] `npm ci && npm run build` so buttons and styles load
- [ ] `php artisan storage:link` (company logo)
- [ ] HTTP-only Nginx config → Certbot → full config
- [ ] `sudo certbot renew --dry-run` passes
- [ ] Queue worker enabled (`sunenterprise-queue`)
- [ ] Cron: `* * * * * php artisan schedule:run`
- [ ] `php artisan app:backup-database` reports an off-site copy
- [ ] One backup test-restored into a scratch database
- [ ] Firewall enabled; only SSH, HTTP and HTTPS open
- [ ] `GET /up` returns 200
- [ ] Text.lk / SMTP credentials set; keep `SMS_ENABLED=false` until a test SMS arrives
- [ ] A real sale printed correctly on the shop printer
- [ ] `.env` contents copied to a password manager
- [ ] Walk through [docs/TESTING.md](TESTING.md) on a staging copy first

## 13. Deploying an update later

```bash
cd /var/www/sunenterprise
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl restart sunenterprise-queue
php artisan up
```

Never run `migrate:fresh` or `db:seed` on a database that already holds live
stock, sales, or projects. Both destroy real data.

## 14. Things to agree with the client

- **Internet outage:** with cloud hosting the shop cannot bill while the
  connection is down. Budget for a second connection (a mobile 4G router on a
  different network) as failover, and keep a paper bill book at the counter.
- **Acceptable data loss:** hourly backups mean a disaster can cost up to one
  hour of sales. Confirm that is acceptable.
- **Ownership:** register the domain and the VPS in the client's name. Agree in
  writing whether the client is buying the source code or a licence to use it.
