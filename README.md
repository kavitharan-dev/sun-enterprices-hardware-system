# Hardware Store & Construction Management System

Integrated full-stack business application connecting hardware store inventory with construction project material workflows.

## Stack

- **Laravel 12** (PHP 8.2+ — Laravel 11+ compatible structure)
- **Blade + Tailwind CSS + Alpine.js**
- **MySQL** (XAMPP) or SQLite (local quick start)
- **Spatie Laravel Permission** (roles)
- **Laravel Breeze** (authentication)

## Documentation

- [Documentation](docs/ARCHITECTURE.md)
- [ERD & Database Design](docs/ERD.md)
- [MVP User Stories](docs/USER_STORIES.md)
- [Testing & go-live](docs/TESTING.md)
- [Production deployment](docs/DEPLOYMENT.md)

## Requirements

- PHP 8.2+
- Composer
- Node.js & NPM
- XAMPP (Apache + MySQL) — optional; SQLite works out of the box

## Quick Start (Local)

### 1. Install dependencies

```bash
composer install
npm install
```

### 2. Environment

Copy `.env.example` to `.env` if needed (already created on install):

```bash
php artisan key:generate
```

**SQLite (default — fastest start):**

```env
DB_CONNECTION=sqlite
```

**MySQL (XAMPP):**

Create database in phpMyAdmin: `hardware_system`

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hardware_system
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Migrate & seed

```bash
php artisan migrate:fresh --seed
```

### 4. Build assets & run

```bash
npm run build
php artisan serve
```

Visit: http://127.0.0.1:8000

## Demo Login Accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@hardware.local | password |
| Store Manager | store@hardware.local | password |
| Cashier | cashier@hardware.local | password |
| Site Manager | site@hardware.local | password |

Public registration is disabled — admin creates users under **Users**.

These are development defaults. `RolePermissionSeeder` sets all four to
`password` and **resets them if re-run**, so change every one after deploying and
never run that seeder on a live database.

## Phase 0 — Foundation

- [x] Laravel project scaffold
- [x] Authentication (login, logout, profile)
- [x] Role-based access (4 roles)
- [x] Responsive sidebar layout
- [x] Role-specific dashboards
- [x] Settings table + default company settings
- [x] Activity logs table + trait
- [x] Documentation (ERD, architecture, user stories)

## Phase 1 — Store catalog, purchases, inventory

- [x] Categories, brands, units
- [x] Products CRUD (SKU, prices, min stock, opening stock)
- [x] Suppliers
- [x] Purchases (draft → receive stock)
- [x] Stock movement ledger (`StockService`)
- [x] Inventory list, movement history, low-stock alerts
- [x] Stock adjustments with required reason
- [x] Sample catalog seed (cement 500 bags, etc.)

**Stock rule:** quantity never changes without a `stock_movements` row.

## Phase 2 — Sales, payments, invoices

- [x] Customers (walk-in sales allowed)
- [x] Sales cart (draft → complete)
- [x] Payments: cash, card, bank transfer, credit, partial
- [x] Printable invoice + PDF download
- [x] Completing a sale writes `sale_out` on the stock ledger
- [x] Cashier can use customers/sales; not products, purchases, or inventory

**Stock rule:** completing a sale never changes quantity without a `stock_movements` row.

## Phase 3 — Projects, workers, material requests

- [x] Projects (admin creates; site manager sees assigned projects only)
- [x] Workers and assign-to-project
- [x] Site manager material requests (draft → submit)
- [x] Store manager approve full/partial quantity or reject with reason
- [x] Site manager cannot approve their own request
- [x] Approving a request does **not** change stock (issuing is Phase 4)

## Phase 4 — Material issue → stock → project

- [x] Store manager issues from an approved request
- [x] Cannot issue more than remaining approved qty or available stock
- [x] Completing an issue writes `material_issue_out` on the stock ledger
- [x] A material `project_expenses` row is created for the issue
- [x] Partial issues mark the request `partially_issued` until fully issued
- [x] Site manager can see issued quantities; cannot issue stock

## Production readiness (added on existing app)

- Text.lk SMS via queued jobs + `sms_logs` (disabled until `SMS_ENABLED=true`)
- Queued email for important alerts; password reset already uses Laravel mail
- Scheduler: low stock, SMS retry/delivery, hourly database backups
- Hourly gzipped backups copied to off-site storage — set `BACKUP_OFFSITE_DISK`
- Audit log: login, products, purchases, stock, price changes
- VPS deploy notes for `sunenterprise.lk` — see [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)

## Phase 6 — Reports, notifications, users

- [x] Store reports: sales, purchases, inventory, stock movements, outstanding (date filter + CSV)
- [x] Construction reports: projects, expenses, material issues
- [x] In-app notification bell and inbox
- [x] Admin user management (one role, deactivate without delete)
- [x] Company settings (name, address, prefixes, logo)
- [x] Activity log viewer with filters
- [x] Admin dashboard sales trend

## Phase 7 — Testing, UI polish, deployment

- [x] Buttons restyled so actions are clearly visible (amber primary, bordered secondary, filled danger/success)
- [x] Regression tests: stock, sales, issues, progress, reports, users, adjustments, health, role gates
- [x] Manual UAT checklist — [docs/TESTING.md](docs/TESTING.md)
- [x] Deployment checklist — [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)

## Development Phases

| Phase | Module |
|-------|--------|
| 0 | Foundation ✅ |
| 1 | Products, suppliers, purchases, inventory ✅ |
| 2 | Sales, payments, invoices ✅ |
| 3 | Projects, workers, material requests ✅ |
| 4 | Material issue → stock → project integration ✅ |
| 5 | Progress, expenses, project dashboard ✅ |
| 6 | Reports, notifications, user management ✅ |
| 7 | Testing & deployment ✅ |

## Project Structure

```
app/
├── Http/Controllers/     # DashboardController, future module controllers
├── Models/               # User, Setting, ActivityLog
├── Traits/               # LogsActivity
config/navigation.php     # Sidebar menu (role-filtered)
docs/                     # Architecture & design docs
resources/views/
├── layouts/              # App layout + sidebar
└── dashboard/            # Role-specific dashboards
```

## License

MIT
