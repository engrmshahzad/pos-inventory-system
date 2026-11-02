<x-app-layout title="Settings">
    @php
        $tabs = ['general' => ['General', 'store'], 'pos' => ['POS & receipt', 'shopping-cart'], 'tax' => ['Tax', 'file-text'], 'alerts' => ['Alerts', 'bell'], 'prefixes' => ['Invoice prefixes', 'receipt'], 'backup' => ['Backup', 'download']];
        $flag = fn ($k) => (bool) (int) $s[$k];
        $prefixLabels = ['prefix_sale' => 'Sale', 'prefix_purchase' => 'Purchase', 'prefix_sale_return' => 'Sale return', 'prefix_purchase_return' => 'Purchase return', 'prefix_transfer' => 'Transfer',
            'prefix_adjustment' => 'Adjustment', 'prefix_stock_count' => 'Stock count', 'prefix_receipt' => 'Customer receipt', 'prefix_payment' => 'Supplier payment', 'prefix_refund' => 'Refund', 'prefix_expense' => 'Expense'];
    @endphp
    <x-page-header title="Settings" subtitle="Business details, POS behaviour, tax, alerts, numbering and backups." :breadcrumbs="['Settings' => null, 'General Settings' => null]" />

    <div class="grid gap-6 lg:grid-cols-[220px_1fr]" x-data="{ tab: @js($tab) }">
        <nav class="card h-fit p-2 lg:sticky lg:top-[86px]" aria-label="Settings sections">
            @foreach ($tabs as $key => [$label, $icon])
                <button type="button" @click="tab = '{{ $key }}'; history.replaceState(null, '', '?tab={{ $key }}')"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"
                    :class="tab === '{{ $key }}' ? 'bg-primary/10 text-primary dark:text-primary-300' : 'text-body hover:bg-subtle hover:text-heading'">
                    <x-icon :name="$icon" size="w-4 h-4" /> {{ $label }}
                </button>
            @endforeach
        </nav>

        <div>
            {{-- General --}}
            <form x-show="tab === 'general'" method="POST" action="{{ route('settings.update', 'general') }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <x-card title="Business" subtitle="Shown on receipts, invoices, statements and the login page.">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-input name="business_name" label="Business name" :value="$s['business_name']" required class="sm:col-span-2" />
                        <x-input name="business_phone" label="Phone" icon="phone" :value="$s['business_phone']" />
                        <x-input name="business_email" type="email" label="Email" icon="mail" :value="$s['business_email']" />
                        <x-input name="business_address" label="Address" icon="map-pin" :value="$s['business_address']" class="sm:col-span-2" />
                        @foreach (['business_logo' => ['Logo', 'PNG/JPG/WEBP, max 1 MB. Used on receipts, invoices and the sidebar.'], 'business_favicon' => ['Favicon', 'Small square PNG or ICO, max 256 KB.']] as $field => [$label, $help])
                            <div x-data="{ remove: false }">
                                <span class="form-label">{{ $label }}</span>
                                <div class="flex items-center gap-3">
                                    @if ($s[$field])<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($s[$field]) }}" alt="" class="h-12 w-12 rounded-lg border border-line bg-white object-contain p-1" x-show="!remove">@endif
                                    <input type="file" name="{{ $field }}" accept="image/*" class="block text-sm text-body file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-primary">
                                    @if ($s[$field])<label class="flex items-center gap-1.5 text-xs text-danger"><input type="checkbox" name="remove_{{ $field }}" value="1" x-model="remove" class="form-checkbox"> Remove</label>@endif
                                </div>
                                <p class="form-help">{{ $help }}</p>
                                @error($field)<p class="form-error">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                </x-card>
                <x-card title="Regional" class="mt-6">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-input name="currency_symbol" label="Currency symbol" :value="$s['currency_symbol']" required />
                        <x-select name="number_format" label="Number format" :options="['international' => '1,250,000 (international)', 'indian' => '12,50,000 (lakh / crore)']" :value="$s['number_format']" />
                        <x-select name="date_format" label="Date format" :options="$dateFormats" :value="$s['date_format']" />
                        <x-select name="timezone" label="Timezone" :options="array_combine($timezones, $timezones)" :value="$s['timezone']" />
                        <x-input name="session_timeout" type="number" min="5" max="1440" label="Auto logout after (minutes idle)" :value="$s['session_timeout']" help="Users are signed out after this much inactivity." />
                    </div>
                    <x-slot name="footer"><div class="flex justify-end"><x-button type="submit" icon="check" loading>Save general settings</x-button></div></x-slot>
                </x-card>
            </form>

            {{-- POS --}}
            <form x-show="tab === 'pos'" x-cloak method="POST" action="{{ route('settings.update', 'pos') }}">
                @csrf @method('PUT')
                <x-card title="Receipt">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-select name="receipt_size" label="Thermal receipt width" :options="['80' => '80 mm', '58' => '58 mm']" :value="$s['receipt_size']" />
                        <div class="flex items-end"><x-toggle name="receipt_show_logo" label="Show logo on receipt" :checked="$flag('receipt_show_logo')" class="w-full" /></div>
                        <x-input name="receipt_header" label="Header text" :value="$s['receipt_header']" placeholder="e.g. Quality products, fair prices" />
                        <x-input name="receipt_footer" label="Footer text" :value="$s['receipt_footer']" />
                    </div>
                </x-card>
                <x-card title="Selling rules" class="mt-6">
                    <div class="space-y-5">
                        <x-toggle name="allow_price_edit" label="Allow price edit on the POS" description="Only for users with the price-edit permission (owner / manager)." :checked="$flag('allow_price_edit')" />
                        <x-toggle name="allow_negative_stock" label="Allow negative stock" description="Let sales continue when the system shows 0 in stock." :checked="$flag('allow_negative_stock')" />
                        <x-toggle name="beep_on_scan" label="Beep on scan" :checked="$flag('beep_on_scan')" />
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-input name="cashier_max_discount" type="number" step="0.5" min="0" max="100" label="Max discount for cashiers (%)" :value="$s['cashier_max_discount']" help="Line + bill discounts together. Checked on the server." />
                            <div>
                                <label class="form-label" for="default_customer_id">Default customer</label>
                                <select id="default_customer_id" name="default_customer_id" placeholder="Walk-in Customer"
                                    x-data x-tomselect="{ valueField: 'id', labelField: 'name', searchField: ['name', 'phone'], allowEmptyOption: true,
                                        load: (q, cb) => fetch(@js(route('customers.search')) + '?q=' + encodeURIComponent(q)).then(r => r.json()).then(cb).catch(() => cb()) }">
                                    <option value="">Walk-in Customer</option>
                                    @if ($defaultCustomer)<option value="{{ $defaultCustomer->id }}" selected>{{ $defaultCustomer->name }}</option>@endif
                                </select>
                                <p class="form-help">Pre-selected on every new POS bill.</p>
                            </div>
                        </div>
                    </div>
                    <x-slot name="footer"><div class="flex justify-end"><x-button type="submit" icon="check" loading>Save POS settings</x-button></div></x-slot>
                </x-card>
            </form>

            {{-- Tax --}}
            <form x-show="tab === 'tax'" x-cloak method="POST" action="{{ route('settings.update', 'tax') }}">
                @csrf @method('PUT')
                <x-card title="Tax" subtitle="Each product has its own tax %. These settings decide how it is applied on the POS.">
                    <div class="space-y-5">
                        <x-toggle name="tax_enabled" label="Charge tax" description="Off: product tax rates are ignored on sales." :checked="$flag('tax_enabled')" />
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-input name="tax_default_rate" type="number" step="0.01" min="0" max="100" label="Default rate for new products (%)" :value="$s['tax_default_rate']" />
                            <x-select name="tax_mode" label="Prices are…" :options="['exclusive' => 'Exclusive — tax is added on top', 'inclusive' => 'Inclusive — price already includes tax']" :value="$s['tax_mode']" />
                        </div>
                        <div class="rounded-xl bg-subtle p-4 text-xs text-body">Example for a Rs 1,000 item at 10%: <b class="text-heading">exclusive</b> → customer pays Rs 1,100; <b class="text-heading">inclusive</b> → customer pays Rs 1,000, of which Rs 90.91 is tax.</div>
                    </div>
                    <x-slot name="footer"><div class="flex justify-end"><x-button type="submit" icon="check" loading>Save tax settings</x-button></div></x-slot>
                </x-card>
            </form>

            {{-- Alerts --}}
            <form x-show="tab === 'alerts'" x-cloak method="POST" action="{{ route('settings.update', 'alerts') }}">
                @csrf @method('PUT')
                <x-card title="Alerts">
                    <div class="space-y-5">
                        <x-toggle name="low_stock_alerts" label="Daily low-stock notification" description="Every morning at 08:00, managers and the owner get a bell notification per branch (needs the scheduler — see Phase 7)." :checked="$flag('low_stock_alerts')" />
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-input name="expiry_warning_days" type="number" min="1" max="365" label="Expiry warning (days)" :value="$s['expiry_warning_days']" />
                            <x-input name="adjustment_approval_threshold" type="number" min="0" label="Adjustment approval above ({{ settings('currency_symbol', 'Rs') }})" :value="$s['adjustment_approval_threshold']" help="Manager adjustments worth more than this wait for the owner." />
                        </div>
                    </div>
                    <x-slot name="footer"><div class="flex justify-end"><x-button type="submit" icon="check" loading>Save alert settings</x-button></div></x-slot>
                </x-card>
            </form>

            {{-- Prefixes --}}
            <form x-show="tab === 'prefixes'" x-cloak method="POST" action="{{ route('settings.update', 'prefixes') }}">
                @csrf @method('PUT')
                <x-card title="Invoice prefixes" subtitle="The middle part of each number: HYD-S-000001. Changing a prefix does not restart numbering or change old documents.">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($prefixLabels as $key => $label)
                            <div x-data="{ v: @js($s[$key]) }">
                                <x-input :name="$key" :label="$label" x-model="v" maxlength="5" style="text-transform: uppercase" required />
                                <p class="form-help font-mono" x-text="{{ in_array($key, ['prefix_transfer']) ? '`${(v || \'\').toUpperCase()}-000001`' : '`HYD-${(v || \'\').toUpperCase()}-000001`' }}"></p>
                            </div>
                        @endforeach
                    </div>
                    <x-slot name="footer"><div class="flex justify-end"><x-button type="submit" icon="check" loading>Save prefixes</x-button></div></x-slot>
                </x-card>
            </form>

            {{-- Backup --}}
            <div x-show="tab === 'backup'" x-cloak>
                <x-card title="Database backup" subtitle="A zip with a full database dump (plus uploaded images). Download it and keep it somewhere safe.">
                    <x-slot name="actions">
                        <form method="POST" action="{{ route('backups.store') }}">@csrf<x-button type="submit" icon="download" loading>Create backup now</x-button></form>
                    </x-slot>
                    <div class="-m-6">
                        <table class="data-table">
                            <thead><tr><th>File</th><th>Created</th><th class="text-right">Size</th><th class="text-right">Actions</th></tr></thead>
                            <tbody>
                                @forelse ($backups as $b)
                                    <tr>
                                        <td class="font-mono text-xs text-heading">{{ $b['name'] }}</td>
                                        <td>{{ fmtDate($b['date'], true) }} <span class="text-xs text-muted">({{ $b['date']->diffForHumans() }})</span></td>
                                        <td class="text-right tabular">{{ number_format($b['size'] / 1024, 1) }} KB</td>
                                        <td class="text-right">
                                            <div class="flex justify-end gap-1">
                                                <x-button size="sm" variant="outline" icon="download" :href="route('backups.download', $b['name'])">Download</x-button>
                                                <form method="POST" action="{{ route('backups.destroy', $b['name']) }}" data-confirm="Delete this backup?">@csrf @method('DELETE')<x-button type="submit" size="icon-sm" variant="ghost-danger" aria-label="Delete"><x-icon name="trash" size="w-4 h-4" /></x-button></form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4"><x-empty-state icon="download" title="No backups yet" description="Create the first one with the button above." /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
                <p class="mt-3 text-xs text-muted">A daily automatic backup runs at 23:30 once the scheduler is set up (Phase 7). Old backups are cleaned up automatically.</p>
            </div>
        </div>
    </div>
</x-app-layout>
