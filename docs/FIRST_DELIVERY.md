# First delivery — handover checklist

Use this on delivery day for **SUN ENTERPRICES**. Tick each box. Do not skip.

---

## A. Before you leave the workshop

- [ ] Code frozen (no new features today)
- [ ] Latest code committed (include Daily Accounts close / print)
- [ ] Local smoke test done: login → sale → Daily Accounts → **Close day** → print / PDF
- [ ] Demo passwords still not used as final customer passwords

---

## B. Server (customer VPS)

- [ ] Domain DNS points at the VPS (`sunenterprise.lk`)
- [ ] Ubuntu + Nginx + PHP 8.2 + MySQL 8 installed (or Forge equivalent)
- [ ] App cloned; `composer install --no-dev`; `npm ci && npm run build`
- [ ] Production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS URL, **MySQL** (not SQLite)
- [ ] `php artisan migrate --force`
- [ ] Seed **only** these four (in order):
  - [ ] `RolePermissionSeeder`
  - [ ] `SettingsSeeder`
  - [ ] `SunEnterpriseProductSeeder`
  - [ ] `SunEnterprisePriceSeeder`
- [ ] **Do not** run full `db:seed` (no demo projects/workers)
- [ ] `storage:link` + `config:cache` + `route:cache` + `view:cache`
- [ ] HTTPS (Certbot) working
- [ ] Queue worker running (`sunenterprise-queue`)
- [ ] Cron: `* * * * * php artisan schedule:run` as `www-data`
- [ ] Off-site backup configured; `php artisan app:backup-database` shows off-site copy
- [ ] `https://…/up` returns OK

Full detail: [DEPLOYMENT.md](DEPLOYMENT.md)

---

## C. Security at handover

- [ ] Change **all four** seeded passwords immediately (admin, store, cashier, site)
- [ ] Create real users for staff; deactivate unused demo accounts if not needed
- [ ] Confirm `RolePermissionSeeder` will **never** be run again on this live DB
- [ ] Keep `SMS_ENABLED=false` until a test SMS works, then enable
- [ ] Copy finished `.env` into a password manager (never into git)

---

## D. Same-day live checks (shop)

- [ ] Admin can log in and open dashboard
- [ ] Cashier: create customer (or walk-in) → complete cash sale → print invoice
- [ ] Cashier: open Daily Accounts → see TXN → **Close day** (optional cash count) → **Print** / **Download PDF**
- [ ] Store manager: receive one purchase (via Daily Accounts) → stock increased
- [ ] Site manager: see only assigned project (if any); cannot open inventory
- [ ] Closed day blocks new money; admin can reopen if needed
- [ ] Paper bill book ready for internet outages

---

## E. Train (30–60 minutes)

| Who | Show |
|-----|------|
| Cashier | Sales, payments, Daily Accounts, close day + print PDF |
| Store manager | Products, purchases, material approve & issue |
| Site manager | Projects, material requests, work sheet |
| Owner / admin | Users, settings, reports, reopen day, activity logs |

- [ ] Training done; staff can repeat one sale and one day close without help

---

## F. Hand over to customer

- [ ] Admin login given to owner only
- [ ] Domain + VPS billed/registered in **customer’s** name
- [ ] Agree: you maintain updates; they own server and data
- [ ] Agree: hourly backup = up to ~1 hour risk in a total server loss
- [ ] Support window for first week (bugs only; no big new features unless agreed)

---

## G. After you leave

- [ ] Watch first 2–3 close-day PDFs for mistakes
- [ ] Confirm nightly/hourly backups still landing off-site
- [ ] Log any bugs; fix and redeploy with `docs/DEPLOYMENT.md` §13 (never `migrate:fresh` / full seed on live)

---

**Delivery complete when A–F are ticked.**  
Manual UAT detail: [TESTING.md](TESTING.md)
