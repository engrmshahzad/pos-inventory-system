# NexaPOS — Multi-Branch Inventory & POS

A production-ready inventory and point-of-sale system for retail businesses with several shops (e.g. Hyderabad, Karachi, Sukkur). Shared product catalogue, stock per branch, fast keyboard POS, purchases, customer *khata* (credit), stock transfers, expenses, reports and role-based access.

Built with **Laravel 12**, MySQL/MariaDB, Blade, **Tailwind CSS 3**, **Alpine.js** and ApexCharts.

> Screenshots: add your own to `docs/screenshots/` — e.g. `dashboard.png`, `pos.png`, `reports.png`.

---

## Features

**Branches & users**
- Unlimited branches with a 3-letter code used in every document number (`HYD-S-000001`)
- Roles: **Owner** (all branches, branch switcher), **Manager** (own branch), **Cashier** (POS only) — enforced on every route and query
- Custom roles (e.g. "Senior Cashier", "Supervisor") with a permission matrix — each is manager-like or cashier-like for data access
- Login by email, 5 attempts/minute limit, strong passwords, idle auto-logout, activity log (owner can delete entries or clear old history)

**Catalogue & stock**
- Products with SKU, EAN-13 barcodes (auto-generated), categories/sub-categories, brands, units (decimal or whole)
- Stock tracked **per branch**; every change written to a stock-movement history
- Excel import (with error report) / export, barcode label printing (38×25, 50×25 mm, A4)
- Stock transfers with send → receive (shortages recorded), owner transfer requests and "suggest transfer"
- Adjustments with owner approval above a value threshold, physical stock count, expiry tracking

**Selling**
- Full-screen POS: barcode scanner, live search, category grid, keyboard shortcuts (F2, F4, F8, F9, F10, +/−, Del)
- Split payments (cash, card, JazzCash, Easypaisa, bank, credit), hold bills, thermal receipt 58/80 mm, A4 PDF, WhatsApp share
- Cash register shifts with expected vs counted cash
- Sale returns (cash refund or khata adjustment), sale cancellation with manager PIN

**Buying & money**
- Suppliers, purchases (received / ordered), purchase returns, product cost = latest purchase cost
- Customer khata and supplier ledgers with running balance, statement PDFs, WhatsApp reminders
- Payments allocated to invoices oldest-first or manually
- Expenses with receipt attachments

**Insight**
- Dashboard per branch or all branches (cached 5 min): KPIs vs yesterday, sales & profit trends, branch comparison, top products, busiest hours…
- 21 reports with date presets, branch filter and **Excel / PDF / print**: sales (6 views), profit & loss, branch comparison, purchases, stock, valuation, low & dead stock, movements, transfers, adjustments, customer & supplier aging, expenses, tax
- Large exports are built in the background and delivered via notification

**Admin**
- Settings: business details & logo, number/date format, timezone, receipt, tax (inclusive/exclusive), cashier discount cap, alerts, invoice prefixes
- One-click database backup + daily automatic backup
- Ctrl+K command palette (pages, products, customers, invoices), in-app notifications, dark mode, mobile layout

---

## Requirements

- PHP **8.2+** with `pdo_mysql`, `mbstring`, `xml`, `fileinfo`, `gd`, `zip`, `bcmath` (optional), `intl` (optional)
- MySQL 8 or MariaDB 10.4+ (and `mysqldump` for backups)
- Composer 2, Node 18+ (only to build the front-end assets)

## Installation (local)

```bash
git clone <your-repo> pos && cd pos
composer install
cp .env.example .env
php artisan key:generate
# edit .env: DB_DATABASE, DB_USERNAME, DB_PASSWORD (and DB_DUMP_PATH if mysqldump is not on PATH)
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve            # http://127.0.0.1:8000
```

Reset to fresh demo data at any time (not allowed in production):

```bash
php artisan pos:demo-reset
```

### Default logins (demo data)

| Role | Email | Password |
|---|---|---|
| Owner | owner@pos.test | password |
| Manager | manager.hyd@pos.test · manager.khi@pos.test · manager.suk@pos.test | password |
| Cashier | cashier.hyd@pos.test · cashier.khi@pos.test · cashier.suk@pos.test | password |

Owner/manager **approval PIN** (to let a cashier cancel a sale): `2468`. Change all passwords and PINs before going live.

## Useful commands

| Command | What it does |
|---|---|
| `php artisan test` | Run the test suite (SQLite in memory) |
| `php artisan pos:demo-reset` | Wipe and reload demo data |
| `php artisan pos:low-stock-alerts` | Send low-stock notifications (runs daily via scheduler) |
| `php artisan pos:benchmark-search` | Time POS search with 10,000 extra products (rolled back) |
| `php artisan backup:run --only-db` | Create a database backup |
| `php artisan queue:work` | Process background jobs (large exports) |
| `php artisan schedule:work` | Run the scheduler locally |

## Folder structure

```
app/
  Console/Commands/     pos:* commands (demo reset, low-stock alerts, benchmark)
  Http/Controllers/     thin controllers, grouped by module (Catalogue, Sales, Purchases, Inventory, Parties, Finance)
  Http/Requests/        form validation
  Models/               Eloquent models
  Policies/             BranchPolicy, UserPolicy, SalePolicy
  Reports/              report engine + one class per report
  Services/             business logic: StockService, SaleService, PurchaseService, TransferService, PaymentService, LedgerService …
  Support/              Menu, Permissions, SettingsDefinition, Sql helpers
database/migrations     schema   ·   database/seeders   realistic Pakistani demo data
resources/views         Blade pages and reusable components (x-card, x-button, x-table …)
resources/js            app.js (Alpine stores, charts), pos.js (POS screen)
deploy/                 nginx, supervisor and cron examples
docs/DEPLOYMENT.md      production guide (cPanel and VPS)
tests/                  feature + unit tests
```

Rules for contributors are in `CLAUDE.md`: all stock changes go through `StockService`, money flows keep `customers.balance` / `suppliers.balance` in sync with `LedgerService`, and branch-scoped data always uses `currentBranchId()`.

## Deployment

See **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** for the production checklist and step-by-step guides for shared cPanel hosting and an Ubuntu VPS (Nginx, PHP-FPM, MySQL, Let's Encrypt).

## Future upgrades (planned, not built)

1. **Offline POS** — installable PWA; cart, products and customers cached in IndexedDB; sales queued offline and synced (with conflict-safe invoice numbers) when the connection returns.
2. **SMS / WhatsApp Business API** — send invoices, payment receipts and due reminders automatically instead of wa.me links.
3. **FBR POS integration** — real-time invoice reporting to FBR (PRAL) with the FBR invoice number and QR code on receipts, for Tier-1 retailers.
4. **Customer loyalty** — points per rupee spent, redemption at the POS, tiers and birthday offers.
5. **Multi-tenant SaaS** — add `tenant_id` to every table with a global scope, sub-domain per business, subscription billing.
6. **Urdu / English** — Laravel localisation with an RTL layout for Urdu.
7. **Batch-level (FIFO) stock** — sell from the oldest batch automatically for exact expiry tracking.

## License

Private / proprietary — © your business. The UI is an original Tailwind design; no paid template code is included.
