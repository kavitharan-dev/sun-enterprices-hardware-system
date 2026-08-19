# Hardware Store & Construction Management System — Architecture

## Overview

Integrated business management system connecting hardware store inventory with construction project material workflows. Built as a responsive full-stack web application using Laravel 11, Blade, Tailwind CSS, Alpine.js, and MySQL.

## Core Principle

**Single inventory ledger** — all stock changes flow through `stock_movements`. Retail sales and construction material issues both reduce the same store inventory.

```
Supplier → Purchase → Stock In → Inventory
                                    ├── Sale → Stock Out → Invoice
                                    └── Material Request → Approve → Issue → Stock Out → Project Expense
```

## Technology Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12 (PHP 8.2+), Laravel 11+ structure |
| Frontend | Blade, Tailwind CSS, Alpine.js, Chart.js |
| Database | MySQL |
| Auth | Laravel Breeze + Spatie Laravel Permission |
| PDF | DomPDF |
| Local dev | XAMPP, Composer, NPM |

## User Roles

| Role | Primary access |
|------|----------------|
| **Admin / Owner** | Full system access |
| **Store Manager** | Products, purchases, inventory, material request approval & issuing |
| **Cashier / Staff** | Sales, customers, payments, invoices |
| **Site Manager** | Assigned projects, material requests, progress, expenses |

## Module Structure

```
app/
├── Enums/              # Status enums, movement types
├── Http/
│   ├── Controllers/
│   │   ├── Store/      # Products, purchases, sales, inventory
│   │   └── Construction/  # Projects, requests, issues, progress
│   └── Requests/       # Form validation
├── Models/             # Eloquent models & relationships
├── Policies/           # Authorization per model
├── Services/           # Business logic (StockService, MaterialIssueService, etc.)
└── Traits/             # LogsActivity, HasDocumentNumber
```

## Critical Services

| Service | Responsibility |
|---------|----------------|
| `StockService` | All stock in/out; updates `stock_movements` + `products.stock_quantity` in one transaction |
| `PurchaseService` | Complete purchase → stock in |
| `SaleService` | Complete sale → stock out → invoice |
| `MaterialIssueService` | Issue from approved request → stock out → project expense |
| `InvoiceService` | PDF invoice generation |
| `DocumentNumberService` | Sequential invoice/request/issue numbers |
| `ReportService` | Aggregated reports with filters |

## Stock Movement Types

- `purchase_in` — Goods received from supplier
- `sale_out` — Retail sale
- `material_issue_out` — Issued to construction project
- `adjustment_in` / `adjustment_out` — Manual correction
- `return_in` / `return_out` — Returns
- `damaged_out` — Write-off
- `opening_balance` — Initial stock setup

## Material Request Workflow

```
draft → pending → approved | partially_approved | rejected
                        ↓
              partially_issued → issued
```

Site Manager creates request → Store Manager reviews → Approve/Reject → Issue materials → Stock decreases → Project expense recorded.

## Security

- Role-based middleware on route groups
- Laravel Policies per model (e.g. Site Manager sees only assigned projects)
- Activity logs on critical actions
- Database transactions on all stock-changing operations

## Development Phases

| Phase | Scope |
|-------|-------|
| 0 | Auth, roles, layout, settings ✅ |
| 1 | Products, suppliers, purchases, stock ledger ✅ |
| 2 | Customers, sales, payments, invoice PDF ✅ |
| 3 | Projects, workers, material requests ✅ |
| 4 | Material issue integration ✅ |
| 5 | Daily progress, expenses, project dashboard ✅ |
| 6 | Reports, notifications, admin dashboard ✅ |
| 7 | Testing, deployment prep ✅ |

## Future (Post-MVP)

- REST API for Flutter mobile app
- Multi-warehouse / multi-branch
- Purchase orders vs goods received
- Email/SMS notifications
- Barcode scanning
- Accounting integration
