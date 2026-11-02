@php
    $user = auth()->user();
    $methods = ['cash' => ['Cash', 'banknote'], 'card' => ['Card', 'wallet'], 'jazzcash' => ['JazzCash', 'phone'], 'easypaisa' => ['Easypaisa', 'phone'], 'bank' => ['Bank Transfer', 'building'], 'credit' => ['Credit (udhaar)', 'file-text']];
    $config = [
        'csrf' => csrf_token(),
        'currency' => settings('currency_symbol', 'Rs'),
        'storageKey' => "pos-cart-{$branch->id}-{$user->id}",
        'beep' => (bool) (int) settings('beep_on_scan', '1'),
        'allowNegative' => (bool) (int) settings('allow_negative_stock', '0'),
        'canEditPrice' => $user->can('sales.edit_price') && (bool) (int) settings('allow_price_edit', '1'),
        'canDiscount' => $user->can('sales.discount'),
        'maxDiscount' => $user->isCashier() ? (float) settings('cashier_max_discount', 5) : null,
        'taxEnabled' => (bool) (int) settings('tax_enabled', '1'),
        'taxInclusive' => settings('tax_mode', 'exclusive') === 'inclusive',
        'defaultCustomer' => ($dc = settings('default_customer_id')) && ($c = \App\Models\Customer::active()->find($dc))
            ? ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'balance' => (float) $c->balance, 'balance_label' => money($c->balance), 'credit_limit' => (float) $c->credit_limit]
            : null,
        'canAddCustomer' => $user->can('customers.create'),
        'heldCount' => $heldCount,
        'categories' => $categories,
        'urls' => [
            'products' => route('pos.products'),
            'lookup' => route('pos.lookup'),
            'checkout' => route('pos.checkout'),
            'hold' => route('pos.hold'),
            'held' => url('pos/held'),
            'customers' => route('pos.customers'),
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.head', ['title' => 'POS · '.$branch->name])
</head>
<body class="lg:h-screen lg:overflow-hidden">
<div x-data="posApp(@js($config))" @keydown.window="onKey($event)" class="flex min-h-screen flex-col lg:h-screen">

    {{-- ===== Top bar ===== --}}
    <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-line bg-card px-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5" title="Back to dashboard">
            <x-logo class="!h-9 !w-9" />
            <span class="hidden text-[15px] font-bold text-heading md:inline">{{ config('app.name') }} <span class="font-medium text-muted">POS</span></span>
        </a>
        <span class="ml-2 inline-flex min-w-0 items-center gap-2 whitespace-nowrap rounded-lg border border-line bg-subtle px-3 py-1.5 text-sm font-semibold text-heading">
            <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $branch->color }}"></span><span class="hidden sm:inline">{{ $branch->name }}</span><span class="sm:hidden">{{ $branch->code }}</span>
        </span>
        <span class="hidden items-center gap-2 text-sm text-body lg:inline-flex"><x-icon name="user" size="w-4 h-4 text-muted" /> {{ $user->name }}</span>

        <div class="ml-auto flex items-center gap-2">
            <span class="hidden rounded-lg bg-subtle px-3 py-1.5 font-mono text-sm font-semibold tabular text-heading sm:inline" x-text="clock"></span>
            <button type="button" @click="openHeld()" class="relative inline-flex h-10 items-center gap-2 rounded-lg border border-line px-3 text-sm font-semibold text-heading hover:bg-subtle">
                <x-icon name="clock" size="w-4 h-4" /> <span class="hidden sm:inline">Hold list</span>
                <span x-show="heldCount" x-text="heldCount" class="absolute -right-1.5 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-warning px-1 text-[11px] font-bold text-white"></span>
            </button>
            <a href="{{ route('register.index') }}" class="hidden h-10 items-center gap-2 rounded-lg border border-line px-3 text-sm font-semibold text-heading hover:bg-subtle md:inline-flex" title="Shift / cash register">
                <x-icon name="wallet" size="w-4 h-4" /> Shift
            </a>
            <button type="button" @click="toggleFullscreen()" class="flex h-10 w-10 items-center justify-center rounded-lg text-body hover:bg-subtle" aria-label="Toggle fullscreen">
                <x-icon name="layout-grid" size="w-5 h-5" />
            </button>
            <button type="button" @click="$store.theme.toggle()" class="flex h-10 w-10 items-center justify-center rounded-lg text-body hover:bg-subtle" aria-label="Toggle dark mode">
                <x-icon name="moon" size="w-5 h-5" x-show="!$store.theme.dark" />
                <x-icon name="sun" size="w-5 h-5" x-show="$store.theme.dark" x-cloak />
            </button>
            <x-button :href="route('dashboard')" variant="outline" icon="chevron-left" class="hidden sm:inline-flex">Dashboard</x-button>
        </div>
    </header>

    <div class="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[minmax(0,3fr)_minmax(380px,2fr)]">
        {{-- ===== Left: search + catalogue ===== --}}
        <section class="flex min-h-0 flex-col bg-page">
            <div class="relative px-4 pt-4">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-primary"><x-icon name="scan" size="w-6 h-6" /></span>
                    <input x-ref="search" x-model="query" @input="onSearchInput()" @keydown.enter.prevent="submitSearch()"
                        @keydown.down.prevent="moveHighlight(1)" @keydown.up.prevent="moveHighlight(-1)"
                        @blur="setTimeout(() => { showResults = false; if (!modal && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName)) focusSearch() }, 150)"
                        type="text" autocomplete="off" placeholder="Scan barcode or search product name / SKU…"
                        class="h-14 w-full rounded-xl border-2 border-primary/30 bg-card pl-14 pr-28 text-base font-medium text-heading shadow-sm placeholder:text-muted focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/15" aria-label="Scan or search products">
                    <span class="absolute inset-y-0 right-4 flex items-center gap-1"><kbd class="kbd">F2</kbd><kbd class="kbd">↵</kbd></span>
                </div>
                {{-- Live results --}}
                <div x-show="showResults" x-cloak class="absolute inset-x-4 top-full z-30 mt-2 overflow-hidden rounded-xl border border-line bg-card shadow-card-hover">
                    <template x-for="(p, i) in results" :key="p.id">
                        <button type="button" @mousedown.prevent="addToCart(p)" @mouseenter="highlighted = i"
                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left" :class="i === highlighted ? 'bg-primary/10' : ''" :disabled="!config.allowNegative && p.stock <= 0">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-sm font-bold" :style="`background:${p.color}1a;color:${p.color}`" x-text="p.name.charAt(0)"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-heading" x-text="p.name"></span>
                                <span class="block text-xs text-muted"><span x-text="p.sku"></span> · <span x-text="p.barcode"></span></span>
                            </span>
                            <span class="text-right">
                                <span class="block font-semibold tabular text-heading" x-text="money(p.price)"></span>
                                <span class="block text-xs tabular" :class="p.stock > 0 ? 'text-muted' : 'text-danger'" x-text="`${qtyLabel(p.stock)} ${p.unit ?? ''} in stock`"></span>
                            </span>
                        </button>
                    </template>
                    <p x-show="!results.length" class="px-4 py-6 text-center text-sm text-muted">No products match “<span x-text="query"></span>”.</p>
                </div>
            </div>

            {{-- Category pills --}}
            <div class="scrollbar-thin flex shrink-0 gap-2 overflow-x-auto px-4 py-3">
                <button type="button" @click="selectCategory(null)" class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition"
                    :class="activeCategory === null ? 'bg-primary text-white shadow-sm shadow-primary/30' : 'bg-card text-body border border-line hover:text-heading'">All</button>
                <template x-for="c in categories" :key="c.id">
                    <button type="button" @click="selectCategory(c.id)" class="flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition"
                        :class="activeCategory === c.id ? 'bg-primary text-white shadow-sm shadow-primary/30' : 'bg-card text-body border border-line hover:text-heading'">
                        <span class="h-2 w-2 rounded-full" :style="`background:${activeCategory === c.id ? '#fff' : c.color}`"></span><span x-text="c.name"></span>
                    </button>
                </template>
            </div>

            {{-- Product grid --}}
            <div class="scrollbar-thin max-h-[60vh] min-h-0 flex-1 overflow-y-auto px-4 pb-4 lg:max-h-none" @scroll.debounce.100ms="if ($el.scrollTop + $el.clientHeight > $el.scrollHeight - 300) loadProducts()">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                    <template x-for="p in products" :key="p.id">
                        <button type="button" @click="addToCart(p)" :disabled="!config.allowNegative && p.stock <= 0"
                            class="group flex flex-col rounded-2xl border border-line bg-card p-3 text-left shadow-card transition duration-150 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-card-hover disabled:cursor-not-allowed disabled:opacity-50 disabled:grayscale disabled:hover:translate-y-0">
                            <div class="relative mb-3 flex aspect-[4/3] items-center justify-center overflow-hidden rounded-xl" :style="`background:${p.color}14`">
                                <template x-if="p.image"><img :src="p.image" alt="" class="h-full w-full object-cover" loading="lazy"></template>
                                <template x-if="!p.image"><span class="text-3xl font-bold" :style="`color:${p.color}`" x-text="p.name.charAt(0)"></span></template>
                                <span class="absolute right-2 top-2 rounded-full px-2 py-0.5 text-[11px] font-bold tabular"
                                    :class="p.stock <= 0 ? 'bg-danger text-white' : (p.stock <= 5 ? 'bg-warning text-white' : 'bg-card/90 text-heading')"
                                    x-text="p.stock <= 0 ? 'Out' : qtyLabel(p.stock)"></span>
                            </div>
                            <span class="line-clamp-2 min-h-[40px] text-sm font-semibold leading-5 text-heading" x-text="p.name"></span>
                            <span class="mt-1 text-[15px] font-bold tabular text-primary dark:text-primary-300" x-text="money(p.price)"></span>
                        </button>
                    </template>
                    <div class="contents" x-show="loadingProducts">
                        <template x-for="n in 8" :key="n">
                            <div class="rounded-2xl border border-line bg-card p-3"><div class="skeleton mb-3 aspect-[4/3] !rounded-xl"></div><div class="skeleton h-3 w-3/4"></div><div class="skeleton mt-2 h-3 w-1/3"></div></div>
                        </template>
                    </div>
                </div>
                <div x-show="!loadingProducts && !products.length"><x-empty-state icon="package" title="No products here" description="Try another category or search." /></div>
            </div>
        </section>

        {{-- ===== Right: cart ===== --}}
        <aside class="flex min-h-0 flex-col border-l border-line bg-card">
            {{-- Customer --}}
            <button type="button" @click="openCustomer()" class="flex shrink-0 items-center gap-3 border-b border-line px-4 py-3 text-left hover:bg-subtle">
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary"><x-icon name="user" size="w-5 h-5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-heading" x-text="cart.customer ? cart.customer.name : 'Walk-in Customer'"></span>
                    <span class="block text-xs text-muted" x-show="!cart.customer">Click to choose a customer</span>
                    <span class="block text-xs" x-show="cart.customer" x-cloak>
                        <span class="text-muted" x-text="cart.customer?.phone"></span>
                        <span x-show="cart.customer?.balance > 0" class="font-semibold text-danger"> · Due <span x-text="money(cart.customer?.balance)"></span></span>
                    </span>
                </span>
                <span x-show="cart.customer" x-cloak @click.stop="cart.customer = null" class="rounded-md p-1 text-muted hover:bg-danger/10 hover:text-danger" title="Remove customer"><x-icon name="x" size="w-4 h-4" /></span>
                <kbd class="kbd">F4</kbd>
            </button>

            {{-- Lines --}}
            <div x-ref="cartList" class="scrollbar-thin min-h-[160px] flex-1 overflow-y-auto lg:min-h-0">
                <template x-for="item in cart.items" :key="item.key">
                    <div class="border-b border-line px-4 py-3 animate-slide-in">
                        <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-heading" x-text="item.name"></p>
                                <div class="mt-0.5 flex items-center gap-1 text-xs text-muted">
                                    <template x-if="config.canEditPrice">
                                        <input type="number" min="0" step="0.01" x-model.number="item.price" class="h-6 w-20 rounded border-line bg-subtle px-1.5 py-0 text-xs tabular text-heading focus:border-primary focus:ring-primary/20" aria-label="Unit price">
                                    </template>
                                    <span x-show="!config.canEditPrice" class="tabular" x-text="money(item.price)"></span>
                                    <span>× <span x-text="item.unit"></span></span>
                                    <template x-if="config.canDiscount">
                                        <span class="ml-2 inline-flex items-center gap-1">disc <input type="number" min="0" step="1" x-model.number="item.discount" class="h-6 w-16 rounded border-line bg-subtle px-1.5 py-0 text-xs tabular text-heading focus:border-primary focus:ring-primary/20" aria-label="Line discount"></span>
                                    </template>
                                </div>
                            </div>
                            <p class="text-right text-sm font-bold tabular text-heading" x-text="money(lineTotal(item))"></p>
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <div class="flex items-center rounded-lg border border-line">
                                <button type="button" @click="changeQty(item, -1)" class="flex h-9 w-9 items-center justify-center text-lg text-body hover:text-heading" aria-label="Decrease">−</button>
                                <input type="number" :step="item.decimal ? '0.001' : '1'" min="0" :value="item.qty" @change="setQty(item, $event.target.value)"
                                    class="h-9 w-16 border-0 bg-transparent p-0 text-center text-sm font-semibold tabular text-heading focus:ring-0" aria-label="Quantity">
                                <button type="button" @click="changeQty(item, 1)" class="flex h-9 w-9 items-center justify-center text-lg text-body hover:text-heading" aria-label="Increase">+</button>
                            </div>
                            <button type="button" @click="removeItem(item)" class="rounded-lg p-2 text-muted hover:bg-danger/10 hover:text-danger" aria-label="Remove"><x-icon name="trash" size="w-4 h-4" /></button>
                        </div>
                    </div>
                </template>
                <div x-show="!cart.items.length" class="flex h-full flex-col items-center justify-center p-8 text-center">
                    <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary"><x-icon name="shopping-cart" size="w-8 h-8" /></span>
                    <p class="mt-4 font-semibold text-heading">The bill is empty</p>
                    <p class="mt-1 text-sm text-muted">Scan a barcode or tap a product to add it.</p>
                    <div class="mt-5 grid grid-cols-2 gap-x-6 gap-y-1.5 text-left text-xs text-muted">
                        <span><kbd class="kbd">F2</kbd> search</span><span><kbd class="kbd">F4</kbd> customer</span>
                        <span><kbd class="kbd">F8</kbd> hold</span><span><kbd class="kbd">F9</kbd> discount</span>
                        <span><kbd class="kbd">F10</kbd> pay</span><span><kbd class="kbd">+</kbd><kbd class="kbd">−</kbd> last qty</span>
                        <span><kbd class="kbd">Del</kbd> remove last</span><span><kbd class="kbd">Esc</kbd> close</span>
                    </div>
                </div>
            </div>

            {{-- Summary --}}
            <div class="shrink-0 border-t border-line bg-subtle/60 px-4 py-3">
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Items</dt><dd class="font-medium tabular text-heading" x-text="`${cart.items.length} lines · ${qtyLabel(itemCount)} qty`"></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd class="font-medium tabular text-heading" x-text="money(subtotal)"></dd></div>
                    @can('sales.discount')
                        <div class="flex items-center justify-between gap-3">
                            <dt class="flex items-center gap-1.5 text-muted">Discount <kbd class="kbd">F9</kbd></dt>
                            <dd class="flex items-center gap-1.5">
                                <div class="flex rounded-md border border-line bg-card p-0.5 text-[11px] font-bold">
                                    <button type="button" @click="cart.discount_type = 'amount'" class="rounded px-1.5 py-0.5" :class="cart.discount_type === 'amount' ? 'bg-primary text-white' : 'text-muted'" x-text="config.currency"></button>
                                    <button type="button" @click="cart.discount_type = 'percent'" class="rounded px-1.5 py-0.5" :class="cart.discount_type === 'percent' ? 'bg-primary text-white' : 'text-muted'">%</button>
                                </div>
                                <input x-ref="discount" type="number" min="0" x-model.number="cart.discount_value" @keydown.enter.prevent="focusSearch()"
                                    class="h-7 w-24 rounded-md border-line bg-card px-2 py-0 text-right text-sm tabular text-heading focus:border-primary focus:ring-primary/20" aria-label="Bill discount">
                            </dd>
                        </div>
                    @endcan
                    <div class="flex justify-between" x-show="tax > 0"><dt class="text-muted">Tax</dt><dd class="font-medium tabular text-heading" x-text="money(tax)"></dd></div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Other charges</dt>
                        <dd><input type="number" min="0" x-model.number="cart.shipping" @keydown.enter.prevent="focusSearch()" class="h-7 w-24 rounded-md border-line bg-card px-2 py-0 text-right text-sm tabular text-heading focus:border-primary focus:ring-primary/20" aria-label="Other charges"></dd>
                    </div>
                </dl>
                <div class="mt-3 flex items-end justify-between border-t border-dashed border-line pt-3">
                    <span class="text-sm font-semibold uppercase tracking-wide text-muted">Grand total</span>
                    <span class="text-3xl font-extrabold tabular text-primary dark:text-primary-300" x-text="money(grandTotal)"></span>
                </div>
                <p x-show="cart.hold_id" x-cloak class="mt-1 text-xs text-warning">Resumed from hold list</p>
                <p x-show="overDiscountCap" x-cloak class="mt-1 text-xs font-semibold text-danger">Discount is above your {{ (float) settings('cashier_max_discount', 5) }}% limit. Ask a manager.</p>
            </div>

            {{-- Actions --}}
            <div class="grid shrink-0 grid-cols-[1fr_1fr_2fr] gap-2 border-t border-line p-3">
                <button type="button" @click="holdBill()" class="flex h-14 flex-col items-center justify-center rounded-xl bg-warning/15 text-sm font-bold text-amber-700 transition hover:bg-warning/25 dark:text-amber-400">
                    Hold <kbd class="kbd mt-0.5 !border-amber-500/30 !bg-transparent">F8</kbd>
                </button>
                <button type="button" @click="clearCart()" class="flex h-14 items-center justify-center rounded-xl text-sm font-bold text-danger transition hover:bg-danger/10">Clear</button>
                <button type="button" @click="openPay()" :disabled="!cart.items.length"
                    class="flex h-14 items-center justify-center gap-3 rounded-xl bg-primary text-lg font-extrabold text-white shadow-lg shadow-primary/30 transition hover:bg-primary-hover disabled:opacity-50">
                    PAY <span class="tabular" x-text="money(grandTotal)"></span> <kbd class="kbd !border-white/30 !bg-white/15 !text-white !shadow-none">F10</kbd>
                </button>
            </div>
        </aside>
    </div>

    @include('pos.partials.modals', ['methods' => $methods])

    @unless ($register)
        @include('pos.partials.open-shift')
    @endunless
</div>
<x-toasts />
</body>
</html>
