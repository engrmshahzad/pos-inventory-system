<x-app-layout title="Units">
    @php
        $blank = ['id' => null, 'name' => '', 'short_name' => '', 'allow_decimal' => false];
        $reopen = $errors->any() && old('_form') === 'unit';
        $initial = $reopen ? array_merge($blank, ['name' => old('name'), 'short_name' => old('short_name'), 'allow_decimal' => (bool) old('allow_decimal'), 'id' => old('_id') ?: null]) : $blank;
    @endphp

    <div x-data="{
            form: @js($initial), blank: @js($blank),
            create() { this.form = { ...this.blank }; $dispatch('open-modal', 'unit-form') },
            edit(u) { this.form = { ...u }; $dispatch('open-modal', 'unit-form') },
        }" x-init="@if ($reopen) $nextTick(() => $dispatch('open-modal', 'unit-form')) @endif">

        <x-page-header title="Units" subtitle="How products are counted and sold." :breadcrumbs="['Inventory' => null, 'Units' => null]">
            <x-button icon="plus" @click="create()">Add Unit</x-button>
        </x-page-header>

        <x-table>
            <x-slot name="head">
                <tr><th>Unit</th><th>Short name</th><th>Decimals</th><th class="text-right">Products</th><th class="text-right">Actions</th></tr>
            </x-slot>
            @foreach ($units as $unit)
                @php $payload = $unit->only(['id', 'name', 'short_name', 'allow_decimal']); @endphp
                <tr>
                    <td class="font-semibold text-heading">{{ $unit->name }}</td>
                    <td><x-badge color="primary">{{ $unit->short_name }}</x-badge></td>
                    <td>
                        @if ($unit->allow_decimal)
                            <x-badge color="info">Allowed (e.g. 1.5)</x-badge>
                        @else
                            <span class="text-muted">Whole numbers</span>
                        @endif
                    </td>
                    <td class="text-right tabular font-semibold text-heading">{{ $unit->products_count }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-button size="icon-sm" variant="ghost" @click="edit({{ \Illuminate\Support\Js::from($payload) }})" aria-label="Edit {{ $unit->name }}"><x-icon name="pencil" size="w-4 h-4" /></x-button>
                            <form method="POST" action="{{ route('units.destroy', $unit) }}" data-confirm="Delete unit {{ $unit->name }}?">
                                @csrf @method('DELETE')
                                <x-button type="submit" size="icon-sm" variant="ghost-danger" aria-label="Delete {{ $unit->name }}"><x-icon name="trash" size="w-4 h-4" /></x-button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            @if ($units->isEmpty())
                <x-slot name="empty"><x-empty-state icon="ruler" title="No units yet" description="Add units like pcs, kg or ltr." /></x-slot>
            @endif
        </x-table>

        <x-modal name="unit-form" title="Unit" maxWidth="md">
            <form id="unit-form" method="POST" class="space-y-5" :action="form.id ? @js(route('units.update', '__ID__')).replace('__ID__', form.id) : @js(route('units.store'))">
                @csrf
                <input type="hidden" name="_method" :value="form.id ? 'PUT' : 'POST'">
                <input type="hidden" name="_form" value="unit">
                <input type="hidden" name="_id" :value="form.id">
                <div class="grid grid-cols-2 gap-4">
                    <x-input name="name" label="Name" placeholder="Kilogram" x-model="form.name" required />
                    <x-input name="short_name" label="Short name" placeholder="kg" x-model="form.short_name" required />
                </div>
                <label class="flex cursor-pointer items-start justify-between gap-4 rounded-xl border border-line p-4">
                    <span>
                        <span class="block text-sm font-medium text-heading">Allow decimal quantities</span>
                        <span class="block text-xs text-muted">Turn on for loose items sold by weight or volume (1.250 kg).</span>
                    </span>
                    <span class="relative inline-flex shrink-0">
                        <input type="hidden" name="allow_decimal" value="0">
                        <input type="checkbox" name="allow_decimal" value="1" class="peer sr-only" x-model="form.allow_decimal">
                        <span class="h-6 w-11 rounded-full bg-line transition peer-checked:bg-primary"></span>
                        <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                    </span>
                </label>
            </form>
            <x-slot name="footer">
                <x-button variant="outline" @click="$dispatch('close-modal', 'unit-form')">Cancel</x-button>
                <x-button type="submit" form="unit-form" icon="check" loading><span x-text="form.id ? 'Save changes' : 'Create unit'"></span></x-button>
            </x-slot>
        </x-modal>
    </div>
</x-app-layout>
