<x-app-layout :title="$transfer->transfer_no">
    @php
        $user = auth()->user();
        [$label, $color] = $transfer->status_badge;
        $canSend = $transfer->status === 'draft' && $user->can('transfers.create') && ($user->isOwner() || $user->branch_id === $transfer->from_branch_id);
        $canReceive = $transfer->status === 'sent' && $user->can('transfers.receive') && ($user->isOwner() || $user->branch_id === $transfer->to_branch_id);
        $canCancel = ($transfer->status === 'draft' && ($user->isOwner() || $transfer->created_by === $user->id || $transfer->from_branch_id === $user->branch_id))
            || ($transfer->status === 'sent' && $user->isOwner() && $user->can('transfers.approve'));
        $done = in_array($transfer->status, ['received', 'partially_received']);
        $steps = [
            ['Created', $transfer->creator, $transfer->created_at, 'file-text', true],
            ['Sent', $transfer->sender, $transfer->sent_at, 'truck', (bool) $transfer->sent_at],
            [$transfer->status === 'partially_received' ? 'Received (short)' : 'Received', $transfer->receiver, $transfer->received_at, 'check-circle', $done],
        ];
        if ($transfer->status === 'cancelled') {
            $steps[] = ['Cancelled', $transfer->canceller, $transfer->cancelled_at, 'x-circle', true];
        }
    @endphp

    <x-page-header :title="$transfer->transfer_no" :subtitle="$transfer->fromBranch->name.' → '.$transfer->toBranch->name.' · '.$transfer->transfer_date->format('d M Y')"
        :breadcrumbs="['Inventory' => null, 'Transfers' => route('transfers.index'), $transfer->transfer_no => null]">
        <x-button :href="route('transfers.challan', $transfer)" target="_blank" variant="outline" icon="file-text">Delivery challan</x-button>
        @if ($canCancel)
            <form method="POST" action="{{ route('transfers.cancel', $transfer) }}" data-confirm="Cancel {{ $transfer->transfer_no }}?"
                data-confirm-text="{{ $transfer->status === 'sent' ? 'Stock will go back to '.$transfer->fromBranch->name.'.' : 'Nothing has moved yet.' }}" data-confirm-button="Cancel transfer">
                @csrf<x-button type="submit" variant="ghost-danger" icon="x">Cancel</x-button>
            </form>
        @endif
        @if ($canSend)
            <form method="POST" action="{{ route('transfers.send', $transfer) }}" data-confirm="Send {{ $transfer->transfer_no }} now?" data-confirm-text="Stock leaves {{ $transfer->fromBranch->name }} and {{ $transfer->toBranch->name }} is notified." data-confirm-button="Send" data-confirm-danger="false">
                @csrf<x-button type="submit" icon="truck" loading>Send now</x-button>
            </form>
        @endif
    </x-page-header>

    @if ($transfer->is_request && $transfer->status === 'draft')
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-300">
            <x-icon name="info" size="w-5 h-5 shrink-0" /> <span>This is a <b>transfer request</b> from {{ $transfer->creator->name }}. {{ $transfer->fromBranch->name }} should check the quantities and press <b>Send now</b>.</span>
        </div>
    @endif

    {{-- Timeline --}}
    <div class="card mb-6 p-6">
        <ol @class(['grid gap-6', 'sm:grid-cols-3' => count($steps) === 3, 'sm:grid-cols-4' => count($steps) === 4])>
            @foreach ($steps as [$stepLabel, $who, $when, $icon, $reached])
                <li class="flex items-start gap-3">
                    <span @class(['flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                        'bg-primary text-white' => $reached && $stepLabel !== 'Cancelled' && $stepLabel !== 'Received (short)',
                        'bg-warning text-white' => $stepLabel === 'Received (short)',
                        'bg-danger text-white' => $stepLabel === 'Cancelled',
                        'bg-subtle text-muted' => ! $reached])><x-icon :name="$icon" size="w-5 h-5" /></span>
                    <div class="min-w-0 shrink-0">
                        <p @class(['text-sm font-semibold', 'text-heading' => $reached, 'text-muted' => ! $reached])>{{ $stepLabel }}</p>
                        @if ($reached && $when)
                            <p class="text-xs text-muted">{{ $when->format('d M Y, h:i A') }}</p>
                            <p class="text-xs text-body">{{ $who?->name }}</p>
                        @else
                            <p class="text-xs text-muted">Waiting</p>
                        @endif
                    </div>
                    @unless ($loop->last)
                        <span @class(['mt-5 hidden h-0.5 flex-1 rounded sm:block', 'bg-primary' => $steps[$loop->index + 1][4], 'bg-line' => ! $steps[$loop->index + 1][4]])></span>
                    @endunless
                </li>
            @endforeach
        </ol>
        @if ($transfer->cancel_reason)<p class="mt-4 text-sm text-danger">Cancel reason: {{ $transfer->cancel_reason }}</p>@endif
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            @if ($canReceive)
                <form method="POST" action="{{ route('transfers.receive', $transfer) }}" data-confirm="Receive {{ $transfer->transfer_no }}?" data-confirm-text="The received quantities will be added to {{ $transfer->toBranch->name }}." data-confirm-button="Receive" data-confirm-danger="false"
                    x-data="{ rows: @js($transfer->items->mapWithKeys(fn ($i) => [$i->id => ['sent' => (float) $i->quantity, 'got' => (float) $i->quantity]])) , short() { return Object.values(this.rows).some(r => Number(r.got) < r.sent) } }">
                    @csrf
            @endif
            <x-card :title="$canReceive ? 'Check and receive' : 'Items'" :subtitle="$canReceive ? 'Enter what actually arrived. Anything less is recorded as a shortage.' : null" padding="p-0">
                <x-slot name="actions"><x-badge :color="$color" dot>{{ $label }}</x-badge></x-slot>
                @error('received')<p class="mx-6 mt-4 rounded-lg bg-danger/10 px-3 py-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Product</th>@if ($transfer->status === 'draft')<th class="text-right">Available at {{ $transfer->fromBranch->code }}</th>@endif<th class="text-right">{{ $transfer->status === 'draft' ? 'Qty' : 'Sent' }}</th>
                            @if ($canReceive)<th class="w-36">Received</th>@elseif ($done)<th class="text-right">Received</th><th class="text-right">Shortage</th>@endif<th>Note</th></tr></thead>
                        <tbody>
                            @foreach ($transfer->items as $item)
                                @php $p = $item->product; @endphp
                                <tr @class(['bg-danger/5' => (float) $item->shortage > 0])>
                                    <td><span class="font-medium text-heading">{{ $p?->name }}</span><span class="block text-xs text-muted">{{ $p?->sku }}</span></td>
                                    @if ($transfer->status === 'draft')
                                        @php $have = (float) ($available[$item->product_id] ?? 0); @endphp
                                        <td class="text-right tabular {{ $have < (float) $item->quantity ? 'font-semibold text-danger' : '' }}">{{ $p?->formatQty($have) }}</td>
                                    @endif
                                    <td class="text-right font-semibold tabular text-heading">{{ $p?->formatQty($item->quantity) }} <span class="text-xs font-normal text-muted">{{ $p?->unit?->short_name }}</span></td>
                                    @if ($canReceive)
                                        <td><input type="number" min="0" max="{{ (float) $item->quantity }}" step="{{ $p?->unit?->allow_decimal ? '0.001' : '1' }}" name="received[{{ $item->id }}]"
                                            x-model.number="rows[{{ $item->id }}].got" class="form-control h-9 tabular" :class="rows[{{ $item->id }}].got < rows[{{ $item->id }}].sent && '!border-warning'" aria-label="Received quantity"></td>
                                    @elseif ($done)
                                        <td class="text-right tabular">{{ $p?->formatQty($item->received_quantity) }}</td>
                                        <td class="text-right font-semibold tabular {{ (float) $item->shortage > 0 ? 'text-danger' : 'text-muted' }}">{{ (float) $item->shortage > 0 ? $p?->formatQty($item->shortage) : '—' }}</td>
                                    @endif
                                    <td class="text-muted">{{ $item->note ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($canReceive)
                    <x-slot name="footer">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <x-input name="receive_note" label="Receiving note" placeholder="e.g. 2 bottles broken in transit" class="flex-1" />
                            <x-button type="submit" variant="success" icon="check" loading><span x-text="short() ? 'Receive with shortage' : 'Receive all'"></span></x-button>
                        </div>
                    </x-slot>
                @endif
            </x-card>
            @if ($canReceive)
                </form>
            @endif
        </div>

        <x-card title="Details">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-muted">From</dt><dd class="font-semibold text-heading">{{ $transfer->fromBranch->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">To</dt><dd class="font-semibold text-heading">{{ $transfer->toBranch->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">Total qty</dt><dd class="font-semibold tabular text-heading">{{ rtrim(rtrim(number_format((float) $transfer->items->sum('quantity'), 3), '0'), '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">Value (cost)</dt><dd class="font-semibold tabular text-heading">{{ money($transfer->items->sum(fn ($i) => (float) $i->quantity * (float) ($i->unit_cost ?: $i->product?->purchase_price)), true) }}</dd></div>
                @if ($done && $transfer->items->sum('shortage') > 0)
                    <div class="flex justify-between"><dt class="text-muted">Shortage value</dt><dd class="font-semibold tabular text-danger">{{ money($transfer->items->sum(fn ($i) => (float) $i->shortage * (float) $i->unit_cost), true) }}</dd></div>
                @endif
                @if ($transfer->note)<div><dt class="text-muted">Note</dt><dd class="text-heading">{{ $transfer->note }}</dd></div>@endif
                @if ($transfer->receive_note)<div><dt class="text-muted">Receiving note</dt><dd class="text-heading">{{ $transfer->receive_note }}</dd></div>@endif
            </dl>
        </x-card>
    </div>
</x-app-layout>
