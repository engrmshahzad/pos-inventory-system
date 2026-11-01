# Project: Multi-Branch Inventory & POS
- Full spec, design system and phases are in prompt.md. Always follow it.
- Stack: Laravel 11, MySQL, Tailwind, Alpine.js.
- Build one phase at a time. Never start the next phase without my approval.

## Progress
- [x] Phase 1
- [ ] Phase 2
- [ ] Phase 3
- [ ] Phase 4
- [ ] Phase 5
- [ ] Phase 6
- [ ] Phase 7

## Conventions (for future phases)
- Branch scoping: always use `currentBranchId()` / `isOwner()` (app/helpers.php); never trust branch_id from the request for non-owners.
- Permissions live in `app/Support/Permissions.php`; re-run `php artisan db:seed --class=RolePermissionSeeder` after adding any.
- Sidebar menu: `app/Support/Menu.php`. Later-phase pages are "Coming soon" routes in `routes/web.php` — replace them with real routes (keep the same route names).
- Stock: change quantities ONLY through `App\Services\StockService` (`increase()` / `decrease()`); it row-locks branch_stock, writes stock_movements and throws `InsufficientStockException`. Wrap multi-line documents (sales, purchases, transfers) in your own `DB::transaction`.
- New products go through `ProductService::create()` (creates branch_stock rows for every branch); new branches through `BranchService::create()` (rows for every product).
- `Product::withStock($branchId)` adds `stock_qty` (null branch = all branches); `products.search` returns JSON for Tom Select and the POS.
- Model changes are logged by the `LogsActivity` trait; use `activity()` for custom events.
- UI: reuse Blade components in `resources/views/components` (x-card, x-button, x-input, x-select, x-table, x-drawer, ...). See /ui-kit.
- Tests: `php artisan test` (SQLite in-memory). Demo logins: owner@pos.test / password.
