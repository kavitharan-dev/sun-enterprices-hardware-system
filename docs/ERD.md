# Entity Relationship Design (MVP)

## Design Principles

1. **Single stock ledger** — `stock_movements` records every change; `products.stock_quantity` is cached and updated in the same transaction.
2. **Price snapshots** — line items store unit price/cost at transaction time.
3. **Polymorphic references** — movements link to Purchase, Sale, MaterialIssue, etc.
4. **Soft deletes** on master data (products, customers, projects, etc.).

---

## Entity Map

```
categories ──< products >── brands
                  │
                  ├── unit_id → units
                  │
    ┌─────────────┼─────────────┐
    ▼             ▼             ▼
purchases      sales    material_issues
    │             │             │
    └─────────────┴─────────────┘
                  ▼
          stock_movements

customers ──< projects ──< material_requests ──< material_request_items
                │                    │
                │                    └──> material_issues
                ├── project_worker >── workers
                ├── project_expenses
                └── daily_progress
```

---

## Tables (32 MVP)

### System & Auth

#### users
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string | |
| email | string unique | |
| phone | string nullable | |
| password | string | |
| is_active | boolean default true | |
| email_verified_at | timestamp nullable | |
| remember_token | string nullable | |
| timestamps | | |

#### roles / permissions
Spatie Laravel Permission package tables.

#### activity_logs
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | FK nullable | |
| action | string | created, approved, issued |
| module | string | Product, MaterialIssue |
| description | text | |
| subject_type | string nullable | Polymorphic |
| subject_id | bigint nullable | |
| properties | json nullable | |
| ip_address | string nullable | |
| timestamps | | |

#### settings
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| key | string unique | company_name, invoice_prefix |
| value | text nullable | |
| group | string default general | |
| timestamps | | |

---

### Store Master Data

#### categories, brands
id, name, slug, description, is_active, sort_order, timestamps, soft_deletes

#### units
id, name, symbol, is_active, timestamps, soft_deletes

#### products
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| sku | string unique | |
| name | string | |
| category_id | FK | |
| brand_id | FK nullable | |
| unit_id | FK | |
| description | text nullable | |
| purchase_price | decimal(12,2) | |
| selling_price | decimal(12,2) | |
| min_stock_level | decimal(12,3) default 0 | |
| stock_quantity | decimal(12,3) default 0 | Cached |
| is_active | boolean default true | |
| timestamps, soft_deletes | | |

**Indexes:** category_id, brand_id, is_active, stock_quantity

#### suppliers
id, name, contact_person, phone, email, address, notes, is_active, timestamps, soft_deletes

#### customers
id, name, phone, email, address, nic nullable, credit_limit, outstanding_balance, is_active, timestamps, soft_deletes

---

### Inventory Ledger

#### stock_movements
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| product_id | FK | |
| movement_type | enum | See below |
| quantity | decimal(12,3) | + in, − out |
| balance_after | decimal(12,3) | |
| unit_cost | decimal(12,2) nullable | |
| reference_type | string nullable | Polymorphic |
| reference_id | bigint nullable | |
| notes | text nullable | |
| user_id | FK | |
| movement_date | date | |
| timestamps | | |

**movement_type:** purchase_in, sale_out, material_issue_out, adjustment_in, adjustment_out, return_in, return_out, damaged_out, opening_balance

**Indexes:** (product_id, movement_date), (reference_type, reference_id), movement_type

---

### Purchases

#### purchases
id, reference_no unique, supplier_id, purchase_date, subtotal, discount, tax, total, status (draft|completed|cancelled), notes, created_by, timestamps, soft_deletes

#### purchase_items
id, purchase_id, product_id, quantity, unit_cost, subtotal, timestamps

---

### Sales

#### sales
id, invoice_no unique nullable, customer_id nullable, sale_date, subtotal, discount, tax, total, paid_amount, balance, payment_status (unpaid|partial|paid), status (draft|completed|cancelled), notes, created_by, timestamps, soft_deletes

#### sale_items
id, sale_id, product_id, quantity, unit_price, discount, subtotal, timestamps

#### payments
id, payable_type, payable_id, amount, payment_method (cash|card|bank_transfer|credit), payment_date, reference nullable, notes, received_by, timestamps

---

### Construction

#### projects
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| project_code | string unique | |
| name | string | |
| customer_id | FK | |
| location | string | |
| description | text nullable | |
| budget | decimal(14,2) | |
| start_date | date | |
| expected_end_date | date nullable | |
| actual_end_date | date nullable | |
| status | enum | planning, active, on_hold, completed, cancelled |
| progress_percentage | decimal(5,2) default 0 | |
| site_manager_id | FK users nullable | |
| created_by | FK | |
| timestamps, soft_deletes | | |

#### workers
id, worker_code unique, name, nic, phone, job_role, daily_rate, join_date nullable, status (active|inactive), timestamps, soft_deletes

#### project_worker
id, project_id, worker_id, role_on_site nullable, assigned_from, assigned_to nullable, timestamps

Unique: (project_id, worker_id, assigned_from)

---

### Material Request → Issue (Integration Core)

#### material_requests
id, request_no unique, project_id, requested_by, request_date, required_date nullable, status, notes, reviewed_by nullable, reviewed_at nullable, rejection_reason nullable, timestamps, soft_deletes

**Status:** draft → pending → approved | partially_approved | rejected → partially_issued → issued

#### material_request_items
id, material_request_id, product_id, quantity_requested, quantity_approved default 0, quantity_issued default 0, notes, timestamps

#### material_issues
id, issue_no unique, project_id, material_request_id nullable, issue_date, issued_by, total_cost, notes, status (completed|cancelled), timestamps, soft_deletes

#### material_issue_items
id, material_issue_id, product_id, quantity, unit_cost, subtotal, material_request_item_id nullable, timestamps

---

### Project Tracking

#### project_expenses
id, project_id, category (material|labour|transport|equipment|electricity|water|other), amount, expense_date, description, reference_type nullable, reference_id nullable, created_by, timestamps, soft_deletes

#### daily_progress
id, project_id, progress_date, work_completed, workers_present default 0, progress_percentage nullable, notes, recorded_by, timestamps

Unique: (project_id, progress_date)

#### daily_progress_materials (Phase 2 optional)
id, daily_progress_id, product_id, quantity, timestamps

---

## Business Rules (Transaction Boundaries)

### Purchase complete
```
BEGIN TRANSACTION
  For each purchase_item:
    INSERT stock_movement (purchase_in, +qty)
    UPDATE product.stock_quantity += qty
COMMIT
```

### Sale complete
```
BEGIN TRANSACTION
  Validate stock for each line
  For each sale_item:
    INSERT stock_movement (sale_out, -qty)
    UPDATE product.stock_quantity -= qty
  Generate invoice_no, update payment_status
COMMIT
```

### Material issue
```
BEGIN TRANSACTION
  Validate approved qty and stock
  CREATE material_issue + items
  For each item:
    INSERT stock_movement (material_issue_out, -qty)
    UPDATE product.stock_quantity -= qty
    UPDATE material_request_item.quantity_issued
  CREATE project_expense (material, ref material_issue)
  UPDATE material_request status
COMMIT
```

---

## Eloquent Relationships (Summary)

| Model | Relationships |
|-------|---------------|
| Product | belongsTo Category, Brand, Unit; hasMany StockMovements, PurchaseItems, SaleItems |
| Purchase | belongsTo Supplier; hasMany PurchaseItems |
| Sale | belongsTo Customer; hasMany SaleItems, Payments |
| Project | belongsTo Customer, User (siteManager); hasMany MaterialRequests, MaterialIssues, Expenses, DailyProgress |
| MaterialRequest | belongsTo Project; hasMany MaterialRequestItems |
| MaterialIssue | belongsTo Project, MaterialRequest; hasMany MaterialIssueItems |
| Worker | belongsToMany Projects via project_worker |
| StockMovement | belongsTo Product, User; morphTo reference |
