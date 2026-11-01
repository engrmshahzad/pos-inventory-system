<x-app-layout title="UI Kit">
    <x-page-header title="UI Kit" subtitle="Every design-system component in one place, in light and dark mode." :breadcrumbs="['Settings' => null, 'UI Kit' => null]">
        <x-button variant="outline" icon="moon" @click="$store.theme.toggle()">Toggle theme</x-button>
    </x-page-header>

    <div x-data="{ tab: 'basics' }">
        {{-- Tabs (underline) --}}
        <div class="mb-6 flex gap-6 overflow-x-auto border-b border-line" role="tablist">
            @foreach (['basics' => 'Basics', 'forms' => 'Forms', 'data' => 'Data display', 'overlays' => 'Overlays & feedback'] as $key => $label)
                <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                    class="-mb-px whitespace-nowrap border-b-2 pb-3 text-sm font-semibold transition"
                    :class="tab === '{{ $key }}' ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-heading'">{{ $label }}</button>
            @endforeach
        </div>

        {{-- BASICS --}}
        <div x-show="tab === 'basics'" class="space-y-6">
            <x-card title="Colours" subtitle="CSS variables mapped into Tailwind (bg-primary, text-heading, border-line…)">
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
                    @foreach (['primary' => '#4F46E5', 'success' => '#10B981', 'warning' => '#F59E0B', 'danger' => '#EF4444', 'info' => '#0EA5E9', 'sidebar' => '#0F172A'] as $name => $hex)
                        <div>
                            <div class="h-16 rounded-xl bg-{{ $name }} shadow-sm"></div>
                            <div class="mt-2 h-6 rounded-lg bg-{{ $name }}/10"></div>
                            <p class="mt-2 text-sm font-semibold capitalize text-heading">{{ $name }}</p>
                            <p class="text-xs text-muted">{{ $hex }}</p>
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card title="Typography">
                <div class="space-y-3">
                    <p class="text-page-title text-heading">Page title · 24px semibold</p>
                    <p class="text-card-title text-heading">Card title · 16px semibold</p>
                    <p class="text-sm text-body">Body text · 14px. The quick brown fox jumps over the lazy dog.</p>
                    <p class="text-sm text-muted">Muted text · used for hints and meta.</p>
                    <p class="section-label">Section label · 12px uppercase</p>
                    <p class="text-kpi tabular text-heading">{{ money(1234567) }} <span class="text-sm font-normal text-muted">tabular numbers</span></p>
                    <p class="text-sm">Keyboard: <kbd class="kbd">F2</kbd> new sale · <kbd class="kbd">Ctrl</kbd> + <kbd class="kbd">K</kbd> search</p>
                </div>
            </x-card>

            <x-card title="Buttons" subtitle="Variants, sizes, icons and loading">
                <div class="space-y-5">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-button>Primary</x-button>
                        <x-button variant="secondary">Secondary</x-button>
                        <x-button variant="outline">Outline</x-button>
                        <x-button variant="ghost">Ghost</x-button>
                        <x-button variant="danger">Danger</x-button>
                        <x-button variant="success">Success</x-button>
                        <x-button variant="warning">Warning</x-button>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-button size="sm" icon="plus">Small</x-button>
                        <x-button icon="plus">Medium</x-button>
                        <x-button size="lg" icon="plus">Large</x-button>
                        <x-button size="icon" variant="outline" aria-label="Settings"><x-icon name="settings" size="w-[18px] h-[18px]" /></x-button>
                        <x-button variant="outline" icon-right="download">Export</x-button>
                        <x-button disabled>Disabled</x-button>
                        <button type="button" data-busy class="group/btn relative inline-flex h-btn items-center rounded-lg bg-primary px-4 text-sm font-semibold text-white" disabled>
                            <span class="absolute inset-0 flex items-center justify-center"><x-icon name="loader" size="w-4 h-4 animate-spin" /></span>
                            <span class="invisible">Loading</span>
                        </button>
                    </div>
                </div>
            </x-card>

            <x-card title="Badges & avatars">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge color="success" dot>Paid</x-badge>
                    <x-badge color="warning" dot>Partial</x-badge>
                    <x-badge color="danger" dot>Due</x-badge>
                    <x-badge color="slate" dot>Pending</x-badge>
                    <x-badge color="primary">HYD</x-badge>
                    <x-badge color="info" icon="truck">In transit</x-badge>
                    <x-badge color="violet">Owner</x-badge>
                </div>
                <div class="mt-5 flex items-center gap-3">
                    <x-avatar name="Ahmed Raza" size="xs" />
                    <x-avatar name="Sana Qureshi" size="sm" />
                    <x-avatar name="Bilal Memon" size="md" />
                    <x-avatar name="Hina Abbasi" size="lg" />
                    <x-avatar name="Imran Siddiqui" size="xl" />
                    <div class="flex -space-x-2">
                        <x-avatar name="A K" size="sm" /><x-avatar name="B M" size="sm" /><x-avatar name="C D" size="sm" />
                    </div>
                </div>
            </x-card>
        </div>

        {{-- FORMS --}}
        <div x-show="tab === 'forms'" x-cloak class="grid gap-6 lg:grid-cols-2">
            <x-card title="Inputs">
                <div class="space-y-5">
                    <x-input name="demo_name" label="Customer name" placeholder="e.g. Ali Traders" required />
                    <x-input name="demo_phone" label="Phone" icon="phone" placeholder="0300-1234567" help="We'll use this for WhatsApp receipts." />
                    <x-input name="demo_password" type="password" label="Password" icon="lock" placeholder="Show / hide" />
                    <div>
                        <label class="form-label">With error</label>
                        <input type="text" class="form-control is-invalid" value="ABCD" aria-invalid="true">
                        <p class="form-error">The code must be exactly 3 letters.</p>
                    </div>
                    <x-input name="demo_note" type="textarea" label="Note" placeholder="Optional note…" />
                    <x-input name="demo_disabled" label="Disabled" value="Read only value" disabled />
                </div>
            </x-card>
            <x-card title="Selects, checkboxes & toggles">
                <div class="space-y-5">
                    <x-select name="demo_city" label="Native select" placeholder="Choose city" :options="['hyd' => 'Hyderabad', 'khi' => 'Karachi', 'suk' => 'Sukkur']" />
                    <x-select name="demo_product" label="Searchable select (Tom Select)" placeholder="Search a product…" searchable
                        :options="['1' => 'Tapal Danedar 950g', '2' => 'Olpers Milk 1L', '3' => 'Dalda Cooking Oil 5L', '4' => 'Surf Excel 1kg', '5' => 'Lifebuoy Soap', '6' => 'Shan Biryani Masala']" />
                    <div class="space-y-2.5">
                        <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" class="form-checkbox" checked> Print receipt after sale</label>
                        <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" class="form-checkbox"> Send on WhatsApp</label>
                        <label class="flex items-center gap-2.5 text-sm"><input type="radio" name="r" class="h-4 w-4 border-line text-primary focus:ring-primary/30" checked> Cash</label>
                        <label class="flex items-center gap-2.5 text-sm"><input type="radio" name="r" class="h-4 w-4 border-line text-primary focus:ring-primary/30"> Credit (udhaar)</label>
                    </div>
                    <x-toggle name="demo_toggle" label="Allow negative stock" description="Let sales continue when stock is zero." />
                    <x-toggle name="demo_toggle2" label="Beep on scan" :checked="true" />
                </div>
            </x-card>
        </div>

        {{-- DATA DISPLAY --}}
        <div x-show="tab === 'data'" x-cloak class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-card label="Today's Sales" :value="money(184250)" icon="banknote" :trend="12.5" :sparkline="[5, 9, 7, 12, 10, 15, 13, 18]" />
                <x-stat-card label="Today's Profit" :value="money(36820)" icon="trending-up" color="success" :trend="8.2" />
                <x-stat-card label="Customer Due" :value="money(245000)" icon="wallet" color="danger" :trend="-4.3" trend-label="vs last month" />
                <x-stat-card label="Low Stock" value="12" icon="alert-triangle" color="warning" href="#" />
            </div>

            <x-table zebra>
                <x-slot name="toolbar">
                    <div class="relative w-full lg:max-w-xs">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                        <input type="search" placeholder="Search invoices…" class="form-control pl-10" aria-label="Search">
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-button variant="outline" icon="filter">Filters</x-button>
                        <x-button variant="outline" icon="calendar">This month</x-button>
                        <x-button variant="outline" icon="download">Export</x-button>
                    </div>
                </x-slot>
                <x-slot name="head">
                    <tr><th class="w-10"><input type="checkbox" class="form-checkbox" aria-label="Select all"></th><th>Invoice</th><th>Customer</th><th class="text-right">Total</th><th>Status</th><th class="text-right">Actions</th></tr>
                </x-slot>
                @foreach ([['HYD-S-000143', 'Walk-in Customer', 4250, 'Paid', 'success'], ['HYD-S-000142', 'Ali Traders', 18600, 'Partial', 'warning'], ['KHI-S-000098', 'Usman General Store', 32400, 'Due', 'danger'], ['SUK-S-000051', 'Walk-in Customer', 980, 'Paid', 'success']] as [$inv, $cust, $total, $status, $color])
                    <tr>
                        <td><input type="checkbox" class="form-checkbox" aria-label="Select {{ $inv }}"></td>
                        <td class="font-semibold text-heading tabular">{{ $inv }}</td>
                        <td><div class="flex items-center gap-2.5"><x-avatar :name="$cust" size="xs" /> {{ $cust }}</div></td>
                        <td class="text-right font-semibold text-heading tabular">{{ money($total) }}</td>
                        <td><x-badge :color="$color" dot>{{ $status }}</x-badge></td>
                        <td class="text-right">
                            <div class="flex justify-end">
                                <x-dropdown width="w-40">
                                    <x-slot name="trigger"><button type="button" class="rounded-lg p-1.5 text-muted hover:bg-subtle" aria-label="Actions"><x-icon name="more-horizontal" size="w-5 h-5" /></button></x-slot>
                                    <x-slot name="content">
                                        <button type="button" class="dropdown-item"><x-icon name="eye" size="w-4 h-4" /> View</button>
                                        <button type="button" class="dropdown-item"><x-icon name="receipt" size="w-4 h-4" /> Print</button>
                                    </x-slot>
                                </x-dropdown>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <div class="grid gap-6 lg:grid-cols-3">
                <x-card title="Area chart" subtitle="Gradient fill, smooth curve" class="lg:col-span-2" padding="px-3 pb-3">
                    <div class="h-[280px]" x-data="apexChart({
                        chart: { type: 'area', height: 280 },
                        series: [{ name: 'Sales', data: [31000, 40000, 28000, 51000, 42000, 109000, 100000] }, { name: 'Profit', data: [11000, 32000, 45000, 32000, 34000, 52000, 41000] }],
                        xaxis: { categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], axisBorder: { show: false }, axisTicks: { show: false } },
                        stroke: { curve: 'smooth', width: 2.5 },
                        fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: 0 } },
                        legend: { position: 'top', horizontalAlign: 'right' },
                        moneyTooltip: true,
                    })"></div>
                </x-card>
                <x-card title="Donut chart" subtitle="Sales by payment method">
                    <div class="h-[260px]" x-data="apexChart({
                        chart: { type: 'donut', height: 260 },
                        series: [52, 18, 14, 9, 7],
                        labels: ['Cash', 'JazzCash', 'Easypaisa', 'Card', 'Credit'],
                        legend: { position: 'bottom' },
                        stroke: { width: 0 },
                        plotOptions: { pie: { donut: { size: '72%' } } },
                    })"></div>
                </x-card>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <x-card title="Empty state">
                    <x-empty-state icon="package" title="No products yet" description="Add your first product to start selling.">
                        <x-button icon="plus">Add product</x-button>
                    </x-empty-state>
                </x-card>
                <x-card title="Skeleton loaders">
                    <div class="space-y-4">
                        @foreach (range(1, 4) as $i)
                            <div class="flex items-center gap-3">
                                <div class="skeleton h-10 w-10 !rounded-full"></div>
                                <div class="flex-1 space-y-2"><div class="skeleton h-3 w-2/3"></div><div class="skeleton h-3 w-1/3"></div></div>
                                <div class="skeleton h-6 w-16 !rounded-full"></div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            </div>
        </div>

        {{-- OVERLAYS --}}
        <div x-show="tab === 'overlays'" x-cloak class="grid gap-6 lg:grid-cols-2">
            <x-card title="Modal & drawer">
                <div class="flex flex-wrap gap-3">
                    <x-button icon="eye" @click="$dispatch('open-modal', 'demo-modal')">Open modal</x-button>
                    <x-button variant="secondary" icon="panel-left" @click="$dispatch('open-drawer', 'demo-drawer')">Open drawer</x-button>
                </div>
            </x-card>
            <x-card title="Toasts">
                <div class="flex flex-wrap gap-3">
                    <x-button variant="success" @click="toast('success', 'Sale HYD-S-000144 saved.')">Success</x-button>
                    <x-button variant="danger" @click="toast('error', 'Insufficient stock for Olpers Milk 1L.')">Error</x-button>
                    <x-button variant="warning" @click="toast('warning', 'Customer is near the credit limit.')">Warning</x-button>
                    <x-button variant="outline" @click="toast('info', 'Transfer TR-000012 is on its way.')">Info</x-button>
                </div>
            </x-card>
            <x-card title="Confirm dialog">
                <p class="mb-4 text-sm text-muted">Real forms use <code class="text-xs text-heading">data-confirm="…"</code>; JS can call <code class="text-xs text-heading">confirmAction()</code>.</p>
                <x-button variant="danger" icon="trash" @click="confirmAction({ title: 'Delete this product?' }).then(ok => ok && toast('success', 'Confirmed! (demo)'))">Delete something</x-button>
            </x-card>
            <x-card title="Dropdown & tooltip">
                <div class="flex items-center gap-4">
                    <x-dropdown align="left">
                        <x-slot name="trigger"><x-button variant="outline" icon-right="chevron-down">Options</x-button></x-slot>
                        <x-slot name="content">
                            <button type="button" class="dropdown-item"><x-icon name="pencil" size="w-4 h-4" /> Edit</button>
                            <button type="button" class="dropdown-item"><x-icon name="download" size="w-4 h-4" /> Export</button>
                            <button type="button" class="dropdown-item text-danger"><x-icon name="trash" size="w-4 h-4" /> Delete</button>
                        </x-slot>
                    </x-dropdown>
                    <span class="group relative inline-flex">
                        <x-button variant="ghost" icon="info">Hover me</x-button>
                        <span role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2.5 py-1.5 text-xs font-medium text-white opacity-0 shadow-lg transition group-hover:opacity-100 dark:bg-slate-700">Tooltip text</span>
                    </span>
                </div>
            </x-card>
        </div>
    </div>

    <x-modal name="demo-modal" title="Add customer" subtitle="Quick create from the POS">
        <div class="space-y-4">
            <x-input name="modal_name" label="Name" placeholder="Customer name" />
            <x-input name="modal_phone" label="Phone" icon="phone" placeholder="0300-1234567" />
        </div>
        <x-slot name="footer">
            <x-button variant="outline" @click="$dispatch('close-modal', 'demo-modal')">Cancel</x-button>
            <x-button icon="check" @click="$dispatch('close-modal', 'demo-modal'); toast('success', 'Customer saved (demo).')">Save</x-button>
        </x-slot>
    </x-modal>

    <x-drawer name="demo-drawer" title="Quick edit" subtitle="Right-side slide-over, 480px">
        <div class="flex-1 space-y-5 overflow-y-auto px-6 py-6">
            <x-input name="drawer_name" label="Product name" value="Tapal Danedar 950g" />
            <div class="grid grid-cols-2 gap-4">
                <x-input name="drawer_cost" label="Purchase price" value="1,450" />
                <x-input name="drawer_price" label="Sale price" value="1,620" />
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-line bg-subtle/60 px-6 py-4">
            <x-button variant="outline" @click="$dispatch('close-drawer', 'demo-drawer')">Cancel</x-button>
            <x-button icon="check" @click="$dispatch('close-drawer', 'demo-drawer'); toast('success', 'Saved (demo).')">Save</x-button>
        </div>
    </x-drawer>
</x-app-layout>
