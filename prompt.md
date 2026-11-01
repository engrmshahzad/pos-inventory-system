Multi-Branch Inventory & POS — AI Build Prompts
Nov 1, 2026 · @Shahzad Choudhary
In prompts ko kaise use karein
Ye 7 phases ke prompts hain; har phase ek AI chat (Claude, Cursor, ChatGPT waghera) mein alag se chalayein, aur har phase test kar ke hi agla shuru karein. Prompts English mein hain kyunke AI coding tools English mein zyada behtar result dete hain.
1. Naye chat mein pehle Master Context Prompt paste karein.
2. Uske baad UI/UX Design System Prompt paste karein.
3. Phir sirf Phase 1 paste karein. Code chala kar test karein.
4. Har naye phase se pehle AI ko batayein: "Phase X complete ho gaya, ab Phase Y start karo." Agar chat lambi ho jaye to naye chat mein Master + Design prompt dobara de kar agla phase dein.
5. Error aaye to poora error message paste karein, sirf "nahi chal raha" na likhein.
Tech stack default Laravel 11 + MySQL + Tailwind CSS + Alpine.js rakha hai. Agar aap MERN ya Django use karte hain to Master Prompt mein sirf "Tech Stack" wala hissa badal dein.
Design Metronic jaisi premium admin feel ka hai, lekin prompt mein original design banwaya gaya hai. Metronic paid template hai, us ka code ya assets copy karna license ke khilaf hai; agar aap ke paas license hai to Phase 1 mein us ki files khud integrate kar sakte hain.
Master Context Prompt (sab se pehle paste karein)
Ye prompt AI ko poora project samjhata hai: kya banana hai, tech stack, roles aur coding rules.
You are a senior full-stack developer and UI engineer. We will build a production-ready, multi-branch Inventory & POS (Point of Sale) web application, phase by phase. Do NOT build everything at once. Wait for me to give each phase. For each phase, deliver complete, working code (no placeholders like "// add logic here"), migrations, seeders, routes, controllers, views and clear setup steps.

## PROJECT OVERVIEW
A retail business owner has multiple shop branches (e.g. Hyderabad, Karachi, Sukkur). The system manages products, per-branch stock, purchases from suppliers, sales through a fast POS screen, customers with credit (udhaar/khata), stock transfers between branches, expenses and reports. Products are a shared catalogue; stock quantity is tracked separately per branch.

## TECH STACK
- Backend: Laravel 11 (PHP 8.2+), MySQL 8
- Frontend: Blade templates + Tailwind CSS 3 + Alpine.js
- Charts: ApexCharts (or Chart.js)
- Icons: Lucide or Heroicons (inline SVG)
- Tables: server-side pagination, search and filters
- PDF: barryvdh/laravel-dompdf; Excel: maatwebsite/excel
- Auth: Laravel Breeze (Blade) customised to our design
- Permissions: spatie/laravel-permission

## ROLES
1. Owner (Super Admin): sees ALL branches, can switch between "All Branches" and any single branch from a top-bar dropdown, manages branches, users, settings.
2. Branch Manager: only their own branch. Manages stock, purchases, transfers, expenses, branch reports, cashiers of their branch.
3. Cashier: only their own branch. Uses POS, creates sales and sale returns, views today's own sales.

## CORE BUSINESS RULES
- Every transactional table has branch_id. The branch filter is ALWAYS applied on the backend from the logged-in user (Owner may choose a branch via session). Never trust branch_id from the request for non-owners.
- Stock lives in branch_stock (branch_id + product_id unique). Every stock change also writes a row to stock_movements (type: opening, purchase, sale, sale_return, purchase_return, transfer_in, transfer_out, adjustment) with +/- quantity and a reference.
- Purchase => stock +, supplier due +. Sale => stock -, customer due + if unpaid. Returns reverse these.
- sale_items stores purchase_price at time of sale so profit stays correct when prices change.
- All multi-table writes (sale, purchase, return, transfer, adjustment) run inside DB::transaction with row locking (lockForUpdate) on branch_stock.
- Negative stock is not allowed by default (setting can allow it).
- Invoice numbers are per branch with a prefix: HYD-S-000001 (sale), HYD-P-000001 (purchase), TR-000001 (transfer).
- When a new product is created, create branch_stock rows with quantity 0 for every branch. When a new branch is created, create rows for every product.
- Money stored as DECIMAL(12,2), quantities as DECIMAL(12,3). Default currency: PKR (Rs).
- Soft deletes on master data (products, customers, suppliers, branches, users).

## DATABASE (main tables)
branches, users (branch_id nullable for owner), categories, brands, units, products, branch_stock, stock_movements, suppliers, customers, purchases, purchase_items, purchase_returns, purchase_return_items, sales, sale_items, sale_returns, sale_return_items, payments (customer/supplier), stock_transfers, stock_transfer_items, stock_adjustments, expense_categories, expenses, settings, activity_logs.

## CODE QUALITY RULES
- Clean architecture: Controllers thin, business logic in Service classes (SaleService, PurchaseService, StockService, TransferService).
- Form Request classes for validation. Policies/permissions on every route.
- A global BranchScope / helper (currentBranchId()) for branch filtering.
- Reusable Blade components: x-card, x-button, x-input, x-select, x-modal, x-badge, x-table, x-stat-card, x-page-header, x-empty-state.
- Consistent naming, comments only where logic is non-obvious.
- Seeders with realistic Pakistani demo data (3 branches, 50 products, 10 suppliers, 20 customers, 1 owner, 1 manager and 1 cashier per branch).
- After each phase, list: files created/changed, commands to run, and how to test.

Reply only with "Ready. Please send the Design System prompt." for now.
UI/UX Design System Prompt (doosra paste)
Ye prompt layout ko premium admin template jaisa banata hai: dark sidebar, saaf cards, gradient header, dark mode. Rang ya font badalna ho to sirf is ke "Colors" aur "Typography" hisse edit karein.
DESIGN SYSTEM — apply to every screen in every phase.

Goal: a modern, premium SaaS admin dashboard look, in the spirit of high-end admin templates (Metronic-style quality): clean, spacious, polished, with subtle depth. Create an ORIGINAL design with Tailwind; do not copy any paid template's code, images or assets.

## LAYOUT
- Left sidebar, fixed, 270px wide, collapsible to an 80px icon-only rail (state saved in localStorage). Dark background (#0F172A / slate-900) with a slightly lighter active item, a 3px primary-coloured left indicator on the active link, rounded-lg items, grouped sections with small uppercase muted labels (MAIN, INVENTORY, SALES, PURCHASES, REPORTS, SETTINGS). Logo + app name at top, user mini-card at bottom.
- Top header, sticky, 70px, white with bottom border and backdrop-blur. Contains: sidebar toggle, global search (Ctrl+K opens a command palette), BRANCH SWITCHER dropdown (Owner only, shows "All Branches" + each branch with a coloured dot), quick "New Sale" primary button, notifications bell with badge, dark-mode toggle, user avatar dropdown (profile, settings, logout).
- Page area: light grey background (#F5F7FB), max content padding 24-32px.
- Page header on each page: title, breadcrumb, short subtitle, and right-aligned action buttons.
- Dashboard top: a hero/welcome banner with a soft primary gradient (indigo to violet), greeting ("Good evening, Ahmed"), today's date, selected branch, and 2-3 quick action buttons.
- Mobile: sidebar becomes an off-canvas drawer with overlay; tables become horizontally scrollable or card lists; touch targets ≥ 44px.

## COLORS (CSS variables + Tailwind config)
- Primary: #4F46E5 (indigo-600), hover #4338CA, soft bg #EEF2FF
- Success: #10B981, Warning: #F59E0B, Danger: #EF4444, Info: #0EA5E9 — each with a soft 10% tint background for badges/icons
- Text: #0F172A headings, #475569 body, #94A3B8 muted
- Borders: #E2E8F0; Cards: #FFFFFF
- Dark mode (class strategy): page #0B1120, cards #111827, borders #1F2937, text #E5E7EB. Every component must support dark mode.

## TYPOGRAPHY
- Font: "Inter" (Google Fonts), fallback system-ui. Numbers in tables/POS use tabular-nums.
- Sizes: page title 24px semibold, card title 16px semibold, body 14px, small/labels 12px medium uppercase tracking-wide for section labels.

## COMPONENTS
- Cards: white, rounded-2xl, 1px border, very soft shadow (0 1px 3px rgba(15,23,42,.06)), hover lift on clickable cards. Card header with title, subtitle and an actions slot.
- Stat/KPI cards: icon in a rounded-xl soft-tinted square, label, big value (28px bold), trend chip (▲ 12.5% green / ▼ red) vs yesterday/last month, optional mini sparkline.
- Buttons: rounded-lg, 40px height; variants primary, secondary (light), outline, ghost, danger, success; sizes sm/md/lg; loading spinner state; icon + text.
- Inputs: 42px height, rounded-lg, border, focus ring 3px primary/20, leading icon support, inline validation messages in red, floating help text.
- Selects: searchable select (Tom Select or Alpine-based) for products, customers, suppliers.
- Tables: clean header (uppercase 12px muted, light bg), row hover, zebra optional, sticky header, checkbox bulk select, status badges, avatar/thumbnail column, row actions in a "⋯" dropdown, top toolbar (search, filters, date range, export buttons), pagination with per-page selector, empty state with illustration-style icon and CTA.
- Badges: pill, soft-tinted (e.g. Paid = green soft, Partial = amber soft, Due = red soft, Pending = slate).
- Modals and slide-over drawers (right side, 480px) for quick create/edit forms.
- Toast notifications top-right (success/error/warning), auto-dismiss 4s.
- Confirm dialogs (SweetAlert2 style) before delete/cancel.
- Tabs (underline style), dropdowns, tooltips, skeleton loaders while data loads.
- Charts: ApexCharts with the brand palette, smooth curves, gradient area fills, no heavy gridlines, tooltips formatted as "Rs 12,500".

## INTERACTION & POLISH
- Smooth 150-200ms transitions; no janky layout shifts.
- Keyboard shortcuts shown as small kbd chips (e.g. F2 = new sale).
- Numbers formatted with thousand separators (Rs 1,25,000 style optional via setting).
- Consistent 8px spacing scale. Generous whitespace.
- Accessibility: visible focus states, aria labels, contrast AA.

Build a /ui-kit page (owner only) that shows every component, so the design can be reviewed.
Reply only with "Design system saved. Send Phase 1." for now.
Phase 1: Setup, Login, Layout, Branches, Users
Is phase ke baad aap ke paas login, khoobsurat layout, branch switcher, aur branches/users manage karne ka system hoga.
PHASE 1 — Foundation, Auth, Layout, Branches & Users

1. Project setup
- Fresh Laravel 11 project, Tailwind + Alpine via Vite, Inter font, ApexCharts, Tom Select, SweetAlert2.
- Install and configure spatie/laravel-permission. Create roles: owner, manager, cashier, and granular permissions (e.g. products.view, products.create, sales.create, reports.view, transfers.approve, settings.manage).
- Tailwind config with the design-system colors, dark mode "class".
- Helper file: currentBranchId(), isOwner(), money($amount), settings($key).

2. Authentication (styled to the design system)
- Split-screen login page: left side brand panel with primary gradient, logo, tagline and a subtle pattern/illustration made with CSS/SVG; right side clean login card (email, password with show/hide, remember me, forgot password).
- Forgot/reset password pages in the same style.
- Inactive users or users of an inactive branch cannot log in (clear error message).
- Log last_login_at and IP.

3. Main layout
- layouts/app.blade.php with the sidebar, sticky header, page header slot, content slot, toasts and modals as defined in the design system.
- Sidebar menu (show/hide per permission): Dashboard; Inventory (Products, Categories, Brands, Units, Stock, Adjustments, Transfers); Sales (POS, Sales List, Sale Returns, Customers); Purchases (Purchases, Purchase Returns, Suppliers); Finance (Payments, Expenses); Reports; Settings (Branches, Users & Roles, General Settings). Items for later phases can link to a "Coming soon" empty-state page.
- Branch switcher (owner only): stores selected branch_id (or "all") in session; a middleware shares the current branch with all views. Non-owners see their branch name as a static chip.
- Dark mode toggle saved in localStorage.
- Placeholder dashboard with the hero banner and 4 KPI cards using dummy values (real data in Phase 6).
- Build all reusable Blade components listed in the master prompt and the /ui-kit page.

4. Branches module (owner only)
- Fields: name, code (3 letters, unique, used as invoice prefix), phone, email, address, city, is_active.
- List as attractive cards (branch name, code badge, city, staff count, active toggle) plus a table view toggle.
- Create/edit in a slide-over drawer. Cannot delete a branch that has transactions (only deactivate).

5. Users module
- Fields: name, email, phone, password, role, branch (required for manager/cashier), avatar upload, is_active.
- Owner manages all users; manager can manage only cashiers of their own branch.
- Table with avatar, role badge, branch, status, last login, actions.
- Profile page: update own info, avatar, password.

6. Activity log
- activity_logs table and a simple trait/helper to log important actions (who, what, branch, IP, time). Viewing page comes in Phase 6.

7. Seeders
- 3 branches (Hyderabad HYD, Karachi KHI, Sukkur SUK), 1 owner (owner@pos.test / password), 1 manager + 1 cashier per branch.

Deliver: migrations, models with relationships, middleware, controllers, Form Requests, Blade views, routes, seeders, and exact commands to run. Then explain how to test each role.
Test karein: teeno roles se login kar ke dekhein ke menu aur data permissions ke hisaab se badalta hai, aur owner branch switch kar sakta hai.
Phase 2: Products, Categories, Stock
Is phase mein product catalogue aur har branch ka alag stock ban jayega.
PHASE 2 — Product Catalogue & Per-Branch Stock

1. Categories, Brands, Units
- Simple CRUD with modals. Category: name, optional parent (one level of sub-categories), icon/colour, is_active. Brand: name, logo. Unit: name, short_name (pcs, kg, ltr, dozen, box), allow_decimal.
- Show product count per item; block delete if products exist.

2. Products
- Fields: name, sku (auto-generate if empty), barcode (auto-generate EAN-13 if empty, unique), category, brand, unit, purchase_price, sale_price, wholesale_price (optional), tax_percent, min_stock (alert level), track_expiry (bool), description, image, is_active.
- On create: create branch_stock rows (qty 0) for every branch; optional "opening stock" inputs per branch on the create form, which write stock_movements type=opening.
- Product list: thumbnail, name + SKU, category, price, total stock (all branches) or current branch stock, stock status badge (In stock / Low / Out), actions. Filters: category, brand, stock status, active. Grid/list view toggle.
- Product detail page with tabs: Overview (info + profit margin %), Stock by Branch (table of every branch's qty), Stock History (stock_movements, paginated), Sales (later).
- Bulk actions: activate/deactivate, delete, print barcodes.
- Import products from Excel/CSV with a downloadable template, validation report of failed rows. Export to Excel.
- Barcode label printing page: choose products + quantity of labels, label size (38x25mm, 50x25mm, A4 sheet), shows name, price, barcode (use picqer/php-barcode-generator), print-friendly CSS.

3. StockService (central, used by all later phases)
- increase(branchId, productId, qty, type, reference) and decrease(...): both lock the branch_stock row, update quantity, write stock_movements, and throw a clear exception on insufficient stock (unless negative stock allowed).
- Unit tests for StockService.

4. Stock page
- Current stock per branch: product, category, qty, min_stock, stock value at purchase price and sale price, status badge.
- Owner on "All Branches" sees a matrix: products as rows, branches as columns, total column.
- Top stat cards: total products, total stock value, low-stock items, out-of-stock items.
- Low stock list with quick "Create Purchase" action (link for Phase 4).
- Stock movement history page with filters: product, type, date range, user.

5. Seeders
- 8 categories, 10 brands, units, 50 realistic products (grocery, beverages, personal care, mobile accessories) with Pakistani-market prices in PKR and opening stock in all 3 branches.

Deliver full code, then test steps.
Test karein: naya product banayein aur check karein ke teeno branches mein us ka stock row ban gaya; barcode print page chala kar dekhein.
Phase 3: POS Screen aur Sales
Ye sab se ahem phase hai; POS screen tez, khoobsurat aur keyboard se chalne wali honi chahiye.
PHASE 3 — POS Screen & Sales

1. POS screen (/pos) — full-screen layout WITHOUT the main sidebar, optimised for speed
- Top bar: logo, branch name chip, cashier name, live clock, "Hold list" button with count badge, fullscreen toggle, back to dashboard.
- LEFT (≈60%): 
  - Big search/scan input, auto-focused at all times. A barcode scanner types like a keyboard + Enter: exact barcode match adds the item instantly (qty +1 if already in cart) with a short beep sound.
  - Typing a name shows a live dropdown (debounced 250ms, AJAX) with name, SKU, price and branch stock.
  - Category filter pills (horizontal scroll) and a product grid of attractive cards (image or coloured initial, name, price, stock badge; out-of-stock cards greyed out).
- RIGHT (≈40%) cart panel, white card, sticky:
  - Customer selector (default "Walk-in Customer") with quick "+ Add customer" modal; shows customer's current due if any.
  - Cart lines: name, unit price (editable if permitted), qty stepper (− / input / +), line discount, line total, remove. Lines animate in.
  - Summary: items count, subtotal, discount (amount or %, whole bill), tax, shipping/other charges, GRAND TOTAL in large bold primary text.
  - Buttons: Hold (amber), Clear (ghost danger), PAY (large primary, full width).
- Keyboard shortcuts with kbd hints: F2 focus search, F4 customer, F8 hold, F9 discount, F10/Enter pay, Esc close modal, + / − change qty of last item, Del remove last item.
- Cart state in Alpine store, also saved to localStorage so a page refresh does not lose the cart.

2. Payment modal
- Payment methods as selectable cards: Cash, Card, JazzCash, Easypaisa, Bank Transfer, Credit (udhaar). Support split payment (e.g. part cash + part JazzCash).
- Cash: amount received input with quick-amount buttons (exact, 500, 1000, 5000) and live CHANGE DUE display.
- Credit/partial allowed only if a real customer is selected; the remaining amount becomes customer due.
- Optional note. Confirm button.

3. Saving a sale (SaleService, inside DB::transaction)
- Generate per-branch invoice number (HYD-S-000123).
- Insert sales + sale_items (store purchase_price snapshot) + payments rows.
- StockService->decrease for each line (type sale).
- Update customer due if unpaid.
- Return sale id; on success show a success modal with "Print Receipt", "New Sale" and "Send on WhatsApp" (wa.me link with a text summary).

4. Receipt
- Thermal receipt view (58mm and 80mm, chosen in settings) with shop logo, branch name/address/phone, invoice no, date/time, cashier, customer, items table, totals, payment breakdown, change, footer message, barcode/QR of invoice no. Auto-open browser print dialog. Also an A4 invoice PDF.

5. Hold bills
- Hold saves cart as a sale with status "hold" (no stock change). Hold list drawer: resume or delete.

6. Sales list & detail
- Table: invoice no, date, branch, customer, cashier, items, total, paid, due, payment status badge, actions (view, print, return). Filters: date range, branch (owner), cashier, payment status, method. Summary cards above the table for the filtered result (total sales, paid, due, profit for owner/manager).
- Sale detail page: nicely formatted invoice view, payments history, "Receive Payment" button for due, reprint.
- Cashier sees only their own today's sales by default.

7. Cash register (simple)
- Cashier opens a shift with opening cash and closes it with counted cash; system shows expected cash (opening + cash sales − cash refunds) and the difference. Shift report printable.

Deliver full code, then test steps including barcode-scanner simulation.
Test karein: keyboard se poora bill banayein (mouse ke baghair), cash aur udhaar dono tareeqon se, aur check karein ke stock sahi branch se kata.
Phase 4: Suppliers, Purchases, Customers, Payments, Returns
Is phase mein maal khareedna, udhaar khata aur returns ka poora hisaab aa jayega.
PHASE 4 — Suppliers, Purchases, Customers (Khata), Payments & Returns

1. Suppliers
- Fields: name, company, phone, email, address, city, NTN (optional), opening_balance, is_active.
- List with current payable balance badge. Supplier profile page with tabs: Overview (stat cards: total purchases, paid, payable), Purchases, Payments, Ledger.

2. Purchases (PurchaseService, transactional)
- Create purchase page: supplier (searchable), branch (owner chooses; manager = own), purchase date, supplier invoice/reference no, status (received / ordered).
- Item table: product search/scan to add rows; qty, purchase price (prefilled), optional new sale price (updates product price if ticked), discount, tax, expiry date + batch no (if product tracks expiry), line total. Add/remove rows smoothly.
- Footer: subtotal, discount, tax, shipping, grand total, paid now (method), due.
- On save with status received: StockService->increase per line (type purchase), supplier payable +, payment row if paid. "Ordered" purchases do not affect stock until marked received.
- Purchase list (filters: supplier, branch, date range, payment status) and detail/print page (A4 PDF).

3. Customers (Khata)
- Fields: name, phone (unique), address, city, CNIC (optional), credit_limit, opening_balance, is_active.
- Warn in POS when a sale would exceed credit_limit.
- Customer profile: stat cards (total purchases, paid, current due, last visit), tabs: Sales, Payments, Ledger.
- Ledger view (both customer and supplier): date, reference, debit, credit, running balance; printable statement PDF for a date range; "Send reminder" WhatsApp link with due amount.

4. Payments
- Receive payment from customer / pay supplier: amount, method, date, reference, note; optionally allocate against specific invoices (oldest first by default).
- Payments list with filters and type badges. Payment receipt print.

5. Sale returns
- Start from a sale (search by invoice no or scan receipt barcode). Show sold items with already-returned qty; choose return qty per line (cannot exceed remaining), reason.
- Refund method: cash refund, or adjust against customer due.
- On save: stock increase (sale_return), refund payment or reduce due, per-branch return number. Print return receipt.

6. Purchase returns
- Same idea from a purchase: stock decrease (purchase_return), supplier payable reduced or refund received.

All lists must follow the design-system table style with summary cards on top. Deliver full code, then test steps.
Test karein: ek purchase karein aur stock barhta dekhein, phir ek customer ko udhaar par bechein, payment receive karein, aur ledger ka running balance check karein.
Phase 5: Stock Transfer aur Adjustments
Multi-branch ka asal faida yahin hai: ek branch se doosri branch maal bhejna, do-step approval ke saath.
PHASE 5 — Stock Transfers Between Branches & Stock Adjustments

1. Stock transfer workflow (TransferService, transactional)
- Statuses: draft → sent (in transit) → received | partially_received | cancelled.
- Create transfer: from branch (manager = own branch; owner chooses), to branch (must differ), date, note. Add products by search/scan; show available qty in the FROM branch next to each row; qty cannot exceed it.
- "Send": decreases FROM branch stock (transfer_out), status sent, notifies the TO branch manager (database notification shown in the header bell).
- TO branch manager opens the transfer and enters received qty per line (defaults to sent qty). "Receive": increases TO branch stock (transfer_in) with received qty. If received < sent, status partially_received and the shortage is recorded and highlighted (and optionally a stock_adjustment with reason "lost in transit").
- Cancel allowed only in draft, or by owner while sent (then stock returns to the FROM branch).
- Transfer number TR-000001. Printable delivery challan (A4) with from/to, items, qty, signatures area.
- Transfer list with tabs: All, Outgoing, Incoming, Pending receive (badge count). Visual status timeline on the detail page (created → sent → received) with user and timestamp for each step.
- Owner can create a transfer request; a "Suggest transfer" helper lists products low in one branch but surplus in another.

2. Stock adjustments
- Types/reasons: damaged, expired, lost/theft, found, correction, internal use.
- Form: branch, date, reason, multiple product lines with + or − qty and note. Manager-created adjustments above a value threshold (setting) need owner approval (pending → approved/rejected).
- On approval: StockService increase/decrease with type adjustment.
- Adjustment list and detail, with total value impact (qty × purchase price).

3. Stock count (physical audit)
- Start a count for a branch (optionally one category); print/scan sheet; enter counted qty; system shows system qty vs counted qty and difference; "Apply" creates one adjustment for all differences.

4. Expiry tracking
- For products with track_expiry, show "Expiring soon" (within N days, setting) and "Expired" lists per branch, with quick adjustment action.

Deliver full code, then test the full transfer flow with two different manager accounts.
Test karein: Hyderabad manager se transfer bhejein, Karachi manager se receive karein, aur thodi kam quantity receive kar ke shortage wala case bhi check karein.
Phase 6: Dashboard, Reports, Expenses, Settings
Is phase mein asal data wala attractive dashboard aur saari reports banengi.
PHASE 6 — Real Dashboard, Expenses, Reports & Settings

1. Expenses
- Expense categories (rent, electricity, salaries, transport, tea/refreshment, maintenance, other) CRUD.
- Expense entry: branch, category, amount, date, payment method, paid to, note, receipt attachment (image/PDF).
- List with filters, category-wise summary donut chart, monthly total.

2. Dashboard (respects branch switcher; cached for 5 minutes)
- Hero banner (from Phase 1) with real greeting, branch and today's date.
- Row of 4 KPI cards with trend vs yesterday: Today's Sales, Today's Profit (owner/manager only), Today's Orders, Today's Expenses. Second row: Customer Due (receivable), Supplier Payable, Stock Value, Low Stock Items.
- Charts (ApexCharts, brand palette, gradient fills):
  - Sales & Profit area chart: last 30 days, toggle 7D / 30D / 12M.
  - Branch comparison bar chart (owner on All Branches): sales and profit per branch this month.
  - Sales by payment method donut.
  - Top 10 products (horizontal bars) and Sales by category donut.
  - Hourly sales heatmap or column chart (busiest hours).
- Widgets: Recent sales table (last 8), Low stock list with progress bars, Pending incoming transfers, Top customers, Expiring soon.
- Cashier dashboard: simplified — today's own sales, orders count, open-shift card, big "Open POS" button.

3. Reports (each: filters at top — date range presets [Today, Yesterday, This week, This month, Last month, Custom], branch, and report-specific filters; summary cards; chart where useful; table; export Excel + PDF + print)
- Sales report (daily/monthly grouping), Sales by product, by category, by cashier, by customer, by payment method.
- Profit & Loss: revenue − cost of goods sold − returns − expenses = net profit, per branch and consolidated.
- Branch comparison report.
- Purchase report (by supplier, by product).
- Stock report, Stock valuation, Low stock, Dead stock (no sale in N days), Stock movement report.
- Transfer report, Adjustment report.
- Customer due (aging: 0-30, 31-60, 61-90, 90+ days) and Supplier payable reports.
- Expense report.
- Tax report (if tax used).
- Activity log viewer (owner).

4. Settings (owner)
- General: business name, logo, favicon, phone, email, address, currency symbol, number format, date format, timezone (default Asia/Karachi).
- POS: receipt size 58/80mm, receipt header/footer text, show logo on receipt, allow price edit, allow negative stock, max discount % for cashier, default customer, beep on scan.
- Tax: enable tax, default rate, inclusive/exclusive.
- Alerts: low-stock email/notification, expiry warning days, adjustment approval threshold.
- Invoice prefixes per document type.
- Backup: one-click database backup download (spatie/laravel-backup) and backup list.
- Settings stored in a settings table with caching.

Deliver full code with efficient queries (indexes, eager loading, aggregate queries — no N+1), then test steps.
Test karein: owner se "All Branches" aur ek ek branch select kar ke dashboard ke numbers compare karein, aur P&L report ka hisaab hath se ek din ka verify karein.
Phase 7: Polish, Security, Testing, Deployment
Aakhri phase software ko client ko dene ya bechne ke qabil banata hai.
PHASE 7 — Polish, Security, Performance, Testing & Deployment

1. Security review
- Audit every controller/route: permission middleware + policies; non-owners can never read or write another branch's data (write a feature test for each module proving this).
- Mass-assignment protection, CSRF everywhere, XSS-safe output, validated file uploads (type/size), rate limiting on login (5 attempts/minute), strong password rules, session timeout setting.
- Sensitive actions (delete, cancel sale, price edit above limit, big discount) logged in activity_logs; cancelling a completed sale requires manager/owner PIN or approval and reverses stock and payments.

2. Performance
- Database indexes on all foreign keys, branch_id + created_at, invoice_no, barcode, sku.
- Eager loading everywhere; dashboard aggregates cached; queue for heavy exports and emails.
- POS product search under 200ms with 10,000 products (indexed search, limited columns).

3. UX polish
- Consistent empty states, loading skeletons, success/error toasts on every action.
- Global command palette (Ctrl+K): jump to any page, search products/customers/invoices.
- Notifications page (low stock, incoming transfers, approvals).
- Print styles checked for receipts, invoices, challans, reports.
- Full dark-mode and mobile responsiveness pass on every page; fix any layout issues.
- Optional: Urdu/English language switch using Laravel localization (RTL support for Urdu).

4. Testing
- PHPUnit/Pest feature tests: auth & roles, branch isolation, sale flow (stock and due correct), purchase flow, returns, transfer flow incl. partial receive, adjustment approval, P&L calculation.
- A seeded demo mode with a "Reset demo data" artisan command.

5. Deployment
- Production .env checklist, `php artisan optimize`, queue worker (Supervisor) and scheduler (cron) setup, daily automatic backup.
- Step-by-step guide for (a) shared cPanel hosting and (b) a VPS (Ubuntu + Nginx + PHP-FPM + MySQL + SSL with Let's Encrypt).
- README.md: features list, screenshots placeholders, installation, default logins, folder structure.

6. Optional future upgrades (just list the plan, don't build): offline POS (PWA + IndexedDB sync), SMS/WhatsApp API invoices, FBR POS integration, customer loyalty points, multi-tenant SaaS conversion (tenant_id).

Start with a list of every issue found in the audit, then the fixes.
Mashwara: har phase ke baad Git commit zaroor karein (git commit -m "Phase 3 complete"), taake AI kuch kharab kare to pichli halat par wapis ja sakein.