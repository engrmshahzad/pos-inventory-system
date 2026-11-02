# Project: Multi-Branch Inventory & POS
- Full spec, design system and phases are in prompt.md. Always follow it.
- Stack: Laravel 12 (upgraded from 11 in Phase 7 for security fixes), MySQL/MariaDB, Tailwind, Alpine.js.
- Build one phase at a time. Never start the next phase without my approval.

## Progress
- [x] Phase 1
- [x] Phase 2
- [x] Phase 3
- [x] Phase 4
- [x] Phase 5
- [x] Phase 6
- [ ] Phase 7 (built and committed; waiting for owner's test sign-off)
- [x] QA audit (2 Nov 2026) — fixed: Brands/Users/Expenses Add+Edit drawers did not open (Alpine `$refs` across component scopes); Stock Count list 500 after applying a count with differences (`adjustment_id` → `stock_adjustment_id`); report PDF out of memory above ~1,000 rows (now capped at `ReportController::PDF_MAX_ROWS` = 500 with a message); Customer/Supplier Delete buttons added. Regression suite: `tests/Feature/ModuleAuditTest.php` (every page × role, CRUD create/update/invalid, deletes).

## Conventions (for future phases)
- Branch scoping: always use `currentBranchId()` / `isOwner()` (app/helpers.php); never trust branch_id from the request for non-owners.
- Permissions live in `app/Support/Permissions.php`; re-run `php artisan db:seed --class=RolePermissionSeeder` after adding any.
- Sidebar menu: `app/Support/Menu.php`. Later-phase pages are "Coming soon" routes in `routes/web.php` — replace them with real routes (keep the same route names).
- Stock: change quantities ONLY through `App\Services\StockService` (`increase()` / `decrease()`); it row-locks branch_stock, writes stock_movements and throws `InsufficientStockException`. Wrap multi-line documents (sales, purchases, transfers) in your own `DB::transaction`.
- New products go through `ProductService::create()` (creates branch_stock rows for every branch); new branches through `BranchService::create()` (rows for every product).
- Sales: create through `SaleService` (`complete()`, `hold()`, `receivePayment()`); it recalculates prices/totals server-side, numbers invoices via `InvoiceNumberService::next('S', $branch)` (row-locked, per branch), decreases stock and updates `customers.balance` (current due).
- `payments` is one table for every money movement (`type`: sale, customer_receipt; Phase 4 adds supplier payments/refunds). Cash payments link to the open `cash_registers` shift so expected cash stays correct; refunds must use `type = 'refund'`.
- Selling requires an open shift (`CashRegister::openFor($user, $branchId)`). Sale visibility: `Sale::visibleTo($user)` + `SalePolicy`.
- `Product::withStock($branchId)` adds `stock_qty` (null branch = all branches); `products.search` returns JSON for Tom Select and the POS.
- Model changes are logged by the `LogsActivity` trait; use `activity()` for custom events.
- UI: reuse Blade components in `resources/views/components` (x-card, x-button, x-input, x-select, x-table, x-drawer, ...). See /ui-kit.
- Purchases via `PurchaseService` (received = stock + supplier payable; ordered waits for `receive()`; product cost = latest purchase cost). General payments via `PaymentService` (allocates oldest-first or manually via `payment_allocations`). Returns via `SaleReturnService` / `PurchaseReturnService`.
- `customers.balance` / `suppliers.balance` are cached running balances; `LedgerService` rebuilds them from documents — keep both in sync in every new money flow (the tests compare them).
- Customers and suppliers are shared by all branches; branch documents (sales, purchases, returns, payments) are branch-scoped via `visibleTo()` scopes and `Controller::ensureBranchAccess()`.
- Transfers via `TransferService` (send = transfer_out at FROM, receive = transfer_in at TO, shortage recorded; owner-only cancel when sent returns stock). Adjustments via `AdjustmentService` (manager above `adjustment_approval_threshold` → pending; stock moves only on approve). Stock counts create one adjustment. In-app notifications: `NotifyService` + `AppNotification` (database channel, shown in the header bell).
- Reports: one class per report in `app/Reports/*` (extend `Report`, return a `ReportResult`), registered in `ReportRegistry`; the controller gives every report date presets, branch filter, Excel/PDF/print. P&L maths lives in `ProfitLossService`, dashboard numbers in `DashboardService` (cached 5 min).
- Settings: every key + default in `App\Support\SettingsDefinition`; read with `settings('key')`. Invoice prefixes come from settings (`InvoiceNumberService`). Tax mode / cashier discount cap / price-edit are enforced in `SaleService`.
- Date columns: filter with start-of-day/end-of-day timestamps (`$ctx->start()`, `$ctx->end()`), not bare `Y-m-d` strings (SQLite tests store dates with a time). Date-part SQL via `App\Support\Sql`.
- DB is MariaDB 10.4: no native `uuid` column type — use `char(36)` (see the notifications migration).
- Model gotcha: DB column defaults are not loaded back after `create()`; set `status` etc. explicitly when code checks them right away.
- Blade gotcha: `@js()` / `@json()` do NOT work inside a component tag's attributes (`<x-button @click="edit(@js($x))">` prints raw text). Use `{{ \Illuminate\Support\Js::from($x) }}` there, or put x-data on a plain element.
- Alpine gotcha: `$refs` only sees refs inside the SAME component. x-modal, x-drawer and x-dropdown each have their own x-data, so page methods called from inside them (e.g. an Edit item in a "⋯" dropdown) cannot reach `$refs` of inputs in the modal — use `document.querySelector(...)` for those.
- Blade gotcha: never put a directive directly after `</x-slot>` on one line (`</x-slot>@endif` breaks compilation) — use a new line.
- Security: SecurityHeaders middleware; no SVG uploads; `App\Support\SafeCell::text()` for every exported text cell; `BranchIsolationTest` must stay green when adding modules (add the new documents to it).
- Sale cancellation: `SaleService::cancel()` (owner/manager, or cashier with a manager PIN via `User::approverByPin()`).
- Exports above `ReportController::QUEUE_THRESHOLD` rows run as `ExportReportJob` (local `.env` uses QUEUE_CONNECTION=sync).
- Deployment: `docs/DEPLOYMENT.md`, `deploy/`, `.env.production.example`.
- Tests: `php artisan test` (SQLite in-memory). Demo logins: owner@pos.test / password.
