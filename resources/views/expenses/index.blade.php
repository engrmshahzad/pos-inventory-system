<x-app-layout title="Expenses">
    @php
        $actor = auth()->user();
        $blank = ['id' => null, 'expense_category_id' => (string) ($categories->firstWhere('is_active', true)?->id ?? ''), 'amount' => '', 'expense_date' => today()->toDateString(), 'method' => 'cash', 'paid_to' => '', 'note' => '', 'attachment_url' => null];
        $reopen = $errors->any() && old('_form') === 'expense';
        $initial = $reopen ? array_merge($blank, \Illuminate\Support\Arr::only(old(), ['expense_category_id', 'amount', 'expense_date', 'method', 'paid_to', 'note']), ['id' => old('_id') ?: null]) : $blank;
        $change = $lastMonth > 0 ? round(($thisMonth - $lastMonth) / $lastMonth * 100, 1) : null;
    @endphp
    <div x-data="{
            form: @js($initial), blank: @js($blank), removeAttachment: false, cat: { id: null, name: '', color: '#4F46E5', is_active: true },
            create() { this.form = { ...this.blank }; this.removeAttachment = false; $refs.file.value = ''; $dispatch('open-drawer', 'expense-form') },
            edit(e) { this.form = { ...e }; this.removeAttachment = false; $refs.file.value = ''; $dispatch('open-drawer', 'expense-form') },
            editCat(c) { this.cat = { ...c }; $dispatch('open-modal', 'category-form') },
        }" x-init="@if ($reopen) $nextTick(() => $dispatch('open-drawer', 'expense-form')) @endif">

        <x-page-header title="Expenses" :subtitle="'Shop running costs'.($currentBranch ? ' at '.$currentBranch->name : ' across all branches').'.'" :breadcrumbs="['Finance' => null, 'Expenses' => null]">
            @can('expenses.edit')<x-button variant="outline" icon="folder" @click="$dispatch('open-modal', 'categories')">Categories</x-button>@endcan
            @can('expenses.create')<x-button icon="plus" @click="create()" :disabled="! $currentBranch" :title="$currentBranch ? null : 'Choose a branch first'">Add Expense</x-button>@endcan
        </x-page-header>

        <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
            <x-stat-card label="In selected period" :value="money($total)" icon="wallet" color="danger" :hint="number_format($count).' entries'" />
            <x-stat-card label="This month" :value="money($thisMonth)" icon="calendar" color="warning" :trend="$change" trend-label="vs last month" />
            <x-stat-card label="Last month" :value="money($lastMonth)" icon="clock" color="info" />
            <x-stat-card label="Biggest category" :value="$byCategory->first()?->name ?? '—'" icon="folder" :hint="$byCategory->first() ? money($byCategory->first()->total) : null" />
        </div>

        <div class="mb-6 grid gap-6 xl:grid-cols-3">
            <x-card title="By category" subtitle="Selected period">
                @if ($byCategory->isNotEmpty())
                    <div class="h-[280px]" x-data="apexChart({ chart: { type: 'donut', height: 280 }, series: @js($byCategory->pluck('total')->map(fn ($v) => (float) $v)), labels: @js($byCategory->pluck('name')),
                        colors: @js($byCategory->pluck('color')), legend: { position: 'bottom' }, stroke: { width: 0 }, plotOptions: { pie: { donut: { size: '68%' } } }, tooltip: { y: { formatter: v => formatMoney(v) } } })"></div>
                @else
                    <x-empty-state icon="wallet" title="No expenses in this period" />
                @endif
            </x-card>
            <x-card title="Monthly total" subtitle="Last 6 months" class="xl:col-span-2" padding="px-3 pb-3 pt-2">
                <div class="h-[280px]" x-data="apexChart({ chart: { type: 'bar', height: 280 }, series: [{ name: 'Expenses', data: @js($monthly->values()) }], xaxis: { categories: @js($monthly->keys()) },
                    plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } }, colors: ['#EF4444'], moneyTooltip: true })"></div>
            </x-card>
        </div>

        <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6 lg:items-center">
            <div class="relative sm:col-span-2">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Number, paid to or note…" class="form-control pl-10" aria-label="Search">
            </div>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control" aria-label="From">
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control" aria-label="To">
            <select name="category" class="form-control" aria-label="Category"><option value="">All categories</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select>
            <div class="flex gap-2">
                @if ($actor->isOwner())
                    <select name="branch" class="form-control" aria-label="Branch"><option value="">All branches</option>@foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch') == $b->id)>{{ $b->name }}</option>@endforeach</select>
                @endif
                <x-button type="submit" icon="filter" size="icon" aria-label="Apply filters" />
            </div>
        </form>

        <x-table :paginator="$expenses">
            <x-slot name="head"><tr><th>Expense</th><th>Date</th><th>Category</th><th>Branch</th><th>Paid to</th><th>Method</th><th class="text-right">Amount</th><th></th><th class="text-right">Actions</th></tr></x-slot>
            @foreach ($expenses as $e)
                @php $payload = ['id' => $e->id, 'expense_category_id' => (string) $e->expense_category_id, 'amount' => (float) $e->amount, 'expense_date' => $e->expense_date->toDateString(), 'method' => $e->method, 'paid_to' => $e->paid_to, 'note' => $e->note, 'attachment_url' => $e->attachment_url]; @endphp
                <tr>
                    <td><span class="font-semibold tabular text-heading">{{ $e->expense_no }}</span>@if ($e->note)<span class="block max-w-[220px] truncate text-xs text-muted">{{ $e->note }}</span>@endif</td>
                    <td class="whitespace-nowrap">{{ fmtDate($e->expense_date) }}</td>
                    <td><span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background: {{ $e->category->color }}"></span>{{ $e->category->name }}</span></td>
                    <td><x-badge color="slate">{{ $e->branch->code }}</x-badge></td>
                    <td>{{ $e->paid_to ?: '—' }}</td>
                    <td>{{ $e->method_label }}@if ($e->cash_register_id)<span class="block text-xs text-muted">from till</span>@endif</td>
                    <td class="whitespace-nowrap text-right font-semibold tabular text-heading">{{ money($e->amount, true) }}</td>
                    <td>@if ($e->attachment_url)<a href="{{ $e->attachment_url }}" target="_blank" class="text-primary" title="View receipt"><x-icon name="file-text" size="w-4 h-4" /></a>@endif</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @can('expenses.edit')<x-button size="icon-sm" variant="ghost" @click="edit({{ \Illuminate\Support\Js::from($payload) }})" aria-label="Edit {{ $e->expense_no }}"><x-icon name="pencil" size="w-4 h-4" /></x-button>@endcan
                            @can('expenses.delete')
                                <form method="POST" action="{{ route('expenses.destroy', $e) }}" data-confirm="Delete {{ $e->expense_no }}?">@csrf @method('DELETE')<x-button type="submit" size="icon-sm" variant="ghost-danger" aria-label="Delete"><x-icon name="trash" size="w-4 h-4" /></x-button></form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
            @if ($expenses->isEmpty())
                <x-slot name="empty"><x-empty-state icon="wallet" title="No expenses found" description="Rent, bijli, salaries, chai — add them to see real profit." /></x-slot>
            @endif
        </x-table>

        {{-- Expense drawer --}}
        <x-drawer name="expense-form" title-text="form.id ? 'Edit expense' : 'New expense'" :subtitle="$currentBranch ? 'Branch: '.$currentBranch->name : null">
            <form method="POST" enctype="multipart/form-data" class="flex flex-1 flex-col overflow-hidden"
                :action="form.id ? @js(route('expenses.update', '__ID__')).replace('__ID__', form.id) : @js(route('expenses.store'))">
                @csrf
                <input type="hidden" name="_method" :value="form.id ? 'PUT' : 'POST'">
                <input type="hidden" name="_form" value="expense"><input type="hidden" name="_id" :value="form.id">
                <input type="hidden" name="remove_attachment" :value="removeAttachment ? 1 : 0">
                <div class="scrollbar-thin flex-1 space-y-5 overflow-y-auto px-6 py-6">
                    <x-select name="expense_category_id" label="Category" :options="$categories->where('is_active', true)->pluck('name', 'id')->all()" x-model="form.expense_category_id" required />
                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="amount" type="number" step="0.01" min="0.01" label="Amount" icon="banknote" x-model="form.amount" required />
                        <x-input name="expense_date" type="date" label="Date" :max="today()->toDateString()" x-model="form.expense_date" required />
                    </div>
                    <x-select name="method" label="Paid by" :options="$methods" x-model="form.method" required help="Cash paid today from an open shift is taken out of that shift's expected cash." />
                    <x-input name="paid_to" label="Paid to" placeholder="e.g. K-Electric, landlord, tea boy" x-model="form.paid_to" />
                    <x-input name="note" type="textarea" label="Note" x-model="form.note" />
                    <div>
                        <span class="form-label">Receipt (image or PDF)</span>
                        <div class="flex items-center gap-3">
                            <x-button size="sm" variant="outline" icon="upload" @click="$refs.file.click()">Attach</x-button>
                            <a x-show="form.attachment_url && !removeAttachment" :href="form.attachment_url" target="_blank" class="text-sm text-primary">View current</a>
                            <button type="button" x-show="form.attachment_url && !removeAttachment" @click="removeAttachment = true" class="text-sm text-danger">Remove</button>
                            <span class="text-xs text-muted" x-ref="fileName"></span>
                        </div>
                        <input type="file" name="attachment" accept="image/*,application/pdf" class="hidden" x-ref="file" @change="$refs.fileName.textContent = $event.target.files[0]?.name ?? ''">
                        @error('attachment')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-line bg-subtle/60 px-6 py-4">
                    <x-button variant="outline" @click="$dispatch('close-drawer', 'expense-form')">Cancel</x-button>
                    <x-button type="submit" icon="check" loading><span x-text="form.id ? 'Save changes' : 'Save expense'"></span></x-button>
                </div>
            </form>
        </x-drawer>

        {{-- Categories --}}
        @can('expenses.edit')
            <x-modal name="categories" title="Expense categories" maxWidth="lg">
                <ul class="-mx-6 -my-5 divide-y divide-line">
                    @foreach ($categories as $c)
                        <li class="flex items-center gap-3 px-6 py-3">
                            <span class="h-3 w-3 rounded-full" style="background: {{ $c->color }}"></span>
                            <span @class(['flex-1 text-sm font-medium', 'text-heading' => $c->is_active, 'text-muted line-through' => ! $c->is_active])>{{ $c->name }}</span>
                            <x-button size="icon-sm" variant="ghost" @click="editCat({{ \Illuminate\Support\Js::from($c->only(['id', 'name', 'color', 'is_active'])) }})" aria-label="Edit"><x-icon name="pencil" size="w-4 h-4" /></x-button>
                            <form method="POST" action="{{ route('expense-categories.destroy', $c) }}" data-confirm="Delete {{ $c->name }}?">@csrf @method('DELETE')<x-button type="submit" size="icon-sm" variant="ghost-danger" aria-label="Delete"><x-icon name="trash" size="w-4 h-4" /></x-button></form>
                        </li>
                    @endforeach
                </ul>
                <x-slot name="footer"><x-button icon="plus" @click="editCat({ id: null, name: '', color: '#4F46E5', is_active: true })">Add category</x-button></x-slot>
            </x-modal>
            <x-modal name="category-form" title="Category" maxWidth="sm">
                <form id="cat-form" method="POST" class="space-y-4" :action="cat.id ? @js(route('expense-categories.update', '__ID__')).replace('__ID__', cat.id) : @js(route('expense-categories.store'))">
                    @csrf <input type="hidden" name="_method" :value="cat.id ? 'PUT' : 'POST'">
                    <div><label class="form-label" for="cat-name">Name</label><input id="cat-name" name="name" x-model="cat.name" class="form-control" required></div>
                    <div class="flex items-center gap-3"><label class="form-label !mb-0" for="cat-color">Colour</label><input id="cat-color" type="color" name="color" x-model="cat.color" class="h-9 w-14 rounded border border-line"></div>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" x-model="cat.is_active" class="form-checkbox"> Active</label>
                </form>
                <x-slot name="footer"><x-button variant="outline" @click="$dispatch('close-modal', 'category-form')">Cancel</x-button><x-button type="submit" form="cat-form" icon="check">Save</x-button></x-slot>
            </x-modal>
        @endcan
    </div>
</x-app-layout>
