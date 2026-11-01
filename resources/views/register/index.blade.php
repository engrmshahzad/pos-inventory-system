<x-app-layout title="Cash Register">
    <x-page-header title="Cash Register" subtitle="Open and close your shift and check expected cash." :breadcrumbs="['Sales' => null, 'Cash Register' => null]">
        @if ($register)<x-button :href="route('pos.index')" icon="shopping-cart">Back to POS</x-button>@endif
    </x-page-header>

    @if (! $currentBranch)
        <x-card class="mb-6"><x-empty-state icon="store" title="Choose a branch" description="Select a branch in the switcher to open or close a shift there." /></x-card>
    @elseif ($register)
        <div class="mb-6 grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <x-stat-card label="Opening cash" :value="money($summary['opening_cash'])" icon="wallet" />
                    <x-stat-card label="Cash taken" :value="money($summary['cash_in'])" icon="banknote" color="success" />
                    <x-stat-card label="Expected in drawer" :value="money($summary['expected_cash'])" icon="check-circle" color="info" />
                    <x-stat-card label="Sales this shift" :value="money($summary['sales_total'])" icon="receipt" color="warning" :hint="$summary['sales_count'].' invoices'" />
                </div>
                <x-card title="By payment method" :subtitle="'Shift opened '.$register->opened_at->format('d M, h:i A').' ('.$register->opened_at->diffForHumans().')'">
                    <dl class="divide-y divide-line text-sm">
                        @foreach ($summary['methods'] as $method)
                            <div class="flex justify-between py-2.5"><dt class="text-muted">{{ $method['label'] }}</dt><dd class="font-semibold tabular text-heading">{{ money($method['total'], true) }}</dd></div>
                        @endforeach
                        <div class="flex justify-between py-2.5"><dt class="text-muted">Credit given (udhaar)</dt><dd class="font-semibold tabular text-danger">{{ money($summary['credit_given'], true) }}</dd></div>
                        <div class="flex justify-between py-2.5"><dt class="text-muted">Cash refunds / paid out</dt><dd class="font-semibold tabular text-danger">-{{ money($summary['cash_refunds'] + $summary['cash_paid_out'], true) }}</dd></div>
                        <div class="flex justify-between py-2.5"><dt class="text-muted">Due received</dt><dd class="font-semibold tabular text-heading">{{ money($summary['received_due'], true) }}</dd></div>
                    </dl>
                </x-card>
            </div>

            <x-card title="Close shift" subtitle="Count the cash in the drawer.">
                <form method="POST" action="{{ route('register.close', $register) }}" class="space-y-4" x-data="{ counted: '' }"
                    data-confirm="Close this shift?" data-confirm-text="You will need to open a new shift to sell again." data-confirm-button="Close shift" data-confirm-danger="false">
                    @csrf
                    <x-input name="counted_cash" type="number" step="0.01" min="0" label="Counted cash" icon="banknote" x-model.number="counted" required />
                    <div class="rounded-xl bg-subtle p-4 text-sm">
                        <div class="flex justify-between"><span class="text-muted">Expected</span><span class="font-semibold tabular text-heading">{{ money($summary['expected_cash'], true) }}</span></div>
                        <div class="mt-1 flex justify-between" x-show="counted !== ''">
                            <span class="text-muted">Difference</span>
                            <span class="font-bold tabular" :class="counted - {{ $summary['expected_cash'] }} < 0 ? 'text-danger' : 'text-emerald-600 dark:text-emerald-400'"
                                x-text="formatMoney(counted - {{ $summary['expected_cash'] }})"></span>
                        </div>
                    </div>
                    <x-input name="closing_note" label="Note" placeholder="Optional, e.g. reason for short cash" />
                    <x-button type="submit" icon="power" class="w-full" loading>Close shift</x-button>
                </form>
            </x-card>
        </div>
    @else
        <x-card class="mb-6" title="No open shift" subtitle="Open a shift to start selling at {{ $currentBranch->name }}.">
            <form method="POST" action="{{ route('register.open') }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                @csrf
                <input type="hidden" name="redirect" value="register">
                <x-input name="opening_cash" type="number" step="0.01" min="0" label="Opening cash" icon="banknote" value="0" required class="sm:w-60" />
                <x-input name="opening_note" label="Note" placeholder="Optional" class="flex-1" />
                <x-button type="submit" icon="check" loading>Open shift</x-button>
            </form>
        </x-card>
    @endif

    <x-table :paginator="$history">
        <x-slot name="head">
            <tr><th>Opened</th><th>Closed</th><th>User</th><th>Branch</th><th class="text-right">Opening</th><th class="text-right">Expected</th><th class="text-right">Counted</th><th class="text-right">Difference</th><th>Status</th><th></th></tr>
        </x-slot>
        @foreach ($history as $shift)
            <tr>
                <td class="whitespace-nowrap">{{ $shift->opened_at->format('d M, h:i A') }}</td>
                <td class="whitespace-nowrap">{{ $shift->closed_at?->format('d M, h:i A') ?? '—' }}</td>
                <td>{{ $shift->user->name }}</td>
                <td><x-badge color="slate">{{ $shift->branch->code }}</x-badge></td>
                <td class="text-right tabular">{{ money($shift->opening_cash) }}</td>
                <td class="text-right tabular">{{ $shift->expected_cash !== null ? money($shift->expected_cash) : '—' }}</td>
                <td class="text-right tabular">{{ $shift->counted_cash !== null ? money($shift->counted_cash) : '—' }}</td>
                <td @class(['text-right font-semibold tabular', 'text-danger' => (float) $shift->difference < 0, 'text-emerald-600 dark:text-emerald-400' => (float) $shift->difference > 0])>
                    {{ $shift->difference !== null ? money($shift->difference) : '—' }}
                </td>
                <td><x-badge :color="$shift->status === 'open' ? 'success' : 'slate'" dot>{{ ucfirst($shift->status) }}</x-badge></td>
                <td class="text-right"><a href="{{ route('register.report', $shift) }}" target="_blank" class="text-sm font-semibold text-primary hover:text-primary-hover">Report</a></td>
            </tr>
        @endforeach
        @if ($history->isEmpty())
            <x-slot name="empty"><x-empty-state icon="wallet" title="No shifts yet" /></x-slot>
        @endif
    </x-table>
</x-app-layout>
