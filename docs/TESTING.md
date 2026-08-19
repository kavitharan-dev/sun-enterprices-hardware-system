# Testing & go-live checklist

Automated tests: `php artisan test` (or `composer test`).

Do **not** run `migrate:fresh` on a database that already has live data.

## Automated coverage

| Area | What is asserted |
|------|------------------|
| Auth | Login, logout, registration disabled, deactivated users blocked |
| Purchases | Complete purchase → stock up + `purchase_in` movement |
| Sales | Complete sale → stock down + `sale_out`; blocked when stock is short; partial payments |
| Material requests | Site manager sees assigned projects only; cannot approve own request |
| Material issues | Issue reduces stock, writes `material_issue_out`, posts project expense |
| Progress / expenses | Assigned site manager can log; duplicate date blocked; material expense cannot be deleted |
| Reports / users | Role-gated reports; admin user create; settings; activity logs; CSV export |
| Inventory | Adjustment in/out writes a movement; reason required; cannot go negative |
| Health | `GET /up` returns OK |

## Manual pass (before production)

Use demo accounts only on a **copy** of the database, never on live stock.

### Admin (`admin@hardware.local`)
- [ ] Login; buttons (Save, Filter, New …) are clearly visible
- [ ] Dashboard shows sales, low stock, projects
- [ ] Create a user, assign one role, deactivate them, confirm they cannot log in
- [ ] Settings: company name appears on invoices
- [ ] Reports: sales + projects, date filter, CSV download
- [ ] Activity logs filter by user/module

### Store manager (`store@hardware.local`)
- [ ] Receive a purchase → inventory increases and a movement exists
- [ ] Approve then issue a material request → stock decreases
- [ ] Cannot open Users or Settings

### Cashier (`cashier@hardware.local`)
- [ ] Create customer, complete a sale, take a partial payment, print invoice
- [ ] Cannot open inventory, products, or reports

### Site manager (`site@hardware.local`)
- [ ] Sees only assigned projects
- [ ] Submit material request; cannot issue stock
- [ ] Log daily progress and a labour expense
- [ ] Project dashboard: budget, spent, remaining

### Stock rule
- [ ] After purchase, sale, issue, and adjustment, `stock_movements` has a matching row and `products.stock_quantity` matches the last `balance_after`

## After deploy

- [ ] `https://sunenterprise.lk/up` returns OK
- [ ] HTTPS redirect works
- [ ] Queue worker is running (`systemctl status sunenterprise-queue`)
- [ ] Scheduler crontab exists for `www-data`
- [ ] Change the seeded admin password immediately
- [ ] `SMS_ENABLED=true` only after Text.lk credentials are verified
