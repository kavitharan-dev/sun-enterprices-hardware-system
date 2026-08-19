# MVP User Stories & Acceptance Criteria

## Admin / Owner

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| A1 | As admin, I manage users and roles | CRUD users; assign one role; deactivate without deleting |
| A2 | As admin, I configure company settings | Set name, address, phone, logo, invoice prefix, currency (Rs.) |
| A3 | As admin, I view the main dashboard | See today's sales, monthly sales, products count, low stock, active projects, pending requests |
| A4 | As admin, I access all reports | Store + construction reports with date filters |
| A5 | As admin, I view activity logs | Filter by user, module, date |

## Store Manager

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| S1 | As store manager, I manage products | CRUD with category, brand, unit, purchase/selling price, min stock |
| S2 | As store manager, I manage suppliers | CRUD supplier records |
| S3 | As store manager, I record purchases | Add line items; on complete → stock increases + movement logged |
| S4 | As store manager, I view inventory | List products with current qty; view movement history per product |
| S5 | As store manager, I review material requests | List pending; approve full/partial qty or reject with reason |
| S6 | As store manager, I issue materials | Issue from approved request; stock decreases; project expense created |
| S7 | As store manager, I see low stock alerts | Dashboard/list shows products below minimum level |

## Cashier / Staff

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| C1 | As cashier, I create a sale | Select products, quantities; cart calculates totals |
| C2 | As cashier, I apply discounts | Line or invoice discount recalculates total |
| C3 | As cashier, I record payments | Cash, card, bank transfer, credit; partial payments supported |
| C4 | As cashier, I generate invoices | Printable/downloadable PDF with business info, line items, totals, balance |
| C5 | As cashier, I manage customers | Create and search customers for sales |

## Site Manager

| ID | User Story | Acceptance Criteria |
|----|------------|---------------------|
| M1 | As site manager, I view my projects | Only projects where I am assigned as site_manager |
| M2 | As site manager, I create material requests | Select project, add products and quantities; submit for approval |
| M3 | As site manager, I track request status | See pending, approved, rejected, partially issued, issued |
| M4 | As site manager, I log daily progress | Date, work completed, workers present, progress %, notes |
| M5 | As site manager, I add project expenses | Labour, transport, equipment, other (material auto from issues) |
| M6 | As site manager, I view project dashboard | Budget, spent, remaining, materials received, recent progress |

## Cross-Cutting (MVP)

- [x] No stock quantity change without a corresponding `stock_movements` record
- [x] Cannot issue more than available stock (default: block; optional admin override)
- [x] Cannot issue more than approved quantity per request line
- [x] Site manager cannot approve their own material request
- [x] Money: 2 decimal places; quantities: up to 3 decimals (e.g. kg)
- [x] Responsive layout on desktop, tablet, and mobile browser
- [x] Critical actions logged in `activity_logs`

## Deferred (Post-MVP)

- Excel export (CSV first in MVP) — CSV is available on reports
- Email/SMS notifications — in-app + Text.lk/email exist; enable SMS in production only
- Multi-warehouse
- Purchase orders vs goods received (GRN)
- Worker payroll
- Budget threshold alerts (80%) — implemented on project expenses
- Flutter mobile app + REST API
- Customer portal
