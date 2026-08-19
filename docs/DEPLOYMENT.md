# Production deployment — SUN ENTERPRICES

The application is ready for Ubuntu + Nginx + PHP 8.2 + MySQL 8 on **https://sunenterprise.lk**.

Do not purchase or configure DNS/SSL from the application. Use this when the VPS and domain are ready.

## 1. Server packages

```bash
sudo apt update
sudo apt install nginx php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd unzip git mysql-server curl
```

PHP-FPM, not XAMPP.

## 2. Application

```bash
sudo mkdir -p /var/www/sunenterprise
sudo chown -R $USER:www-data /var/www/sunenterprise
cd /var/www/sunenterprise
git clone <your-repo-url> .
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

## 3. Production `.env` (required)

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

Never commit real credentials.

## 4. Database

```bash
php artisan migrate --force
php artisan db:seed --force   # first deploy only
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Existing migrations create the full schema on a fresh MySQL 8 server, including foreign keys and indexes.

## 5. Nginx + SSL

Copy `deploy/nginx/sunenterprise.lk.conf` to `/etc/nginx/sites-available/sunenterprise.lk`, enable the site, then:

```bash
sudo certbot --nginx -d sunenterprise.lk -d www.sunenterprise.lk
sudo nginx -t && sudo systemctl reload nginx
```

## 6. Queue worker (SMS + email)

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

## 7. Scheduler

```bash
sudo crontab -e -u www-data
```

```
* * * * * cd /var/www/sunenterprise && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs:

- Low-stock checks (08:00 Asia/Colombo)
- Failed SMS retry + delivery status (every 15 minutes)
- Overdue payment and project deadline alerts (08:30 Asia/Colombo)
- Database backup (02:15) → `storage/app/private/backups`

## 8. Permissions

```bash
sudo chown -R www-data:www-data /var/www/sunenterprise/storage /var/www/sunenterprise/bootstrap/cache
sudo chmod -R ug+rwx /var/www/sunenterprise/storage /var/www/sunenterprise/bootstrap/cache
```

## 9. Health check

`https://sunenterprise.lk/up`

## 10. Go-live checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://sunenterprise.lk`
- [ ] MySQL 8 database created; `php artisan migrate --force` (not `migrate:fresh`)
- [ ] `npm ci && npm run build` so buttons and styles load
- [ ] `php artisan storage:link` (company logo)
- [ ] Nginx config from `deploy/nginx/sunenterprise.lk.conf` + Certbot SSL
- [ ] Queue worker enabled (`sunenterprise-queue`)
- [ ] Cron: `* * * * * php artisan schedule:run`
- [ ] `GET /up` returns 200
- [ ] Change `admin@hardware.local` password
- [ ] Text.lk / SMTP credentials set; keep `SMS_ENABLED=false` until a test SMS succeeds
- [ ] Walk through [docs/TESTING.md](TESTING.md) on a staging copy first

Never run `migrate:fresh` or `db:seed` on a database that already has live stock, sales, or projects.
