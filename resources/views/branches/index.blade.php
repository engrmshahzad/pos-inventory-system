<x-app-layout title="Branches">
    @php
        $blank = ['id' => null, 'name' => '', 'code' => '', 'phone' => '', 'email' => '', 'address' => '', 'city' => '', 'is_active' => true];
        $reopen = $errors->any() && old('_form') === 'branch';
        $initial = $reopen ? array_merge($blank, old(), ['id' => old('_id') ?: null, 'is_active' => (bool) old('is_active')]) : $blank;
    @endphp

    <div x-data="{
            view: (() => { try { return localStorage.getItem('branches-view') || 'cards' } catch (e) { return 'cards' } })(),
            form: @js($initial),
            blank: @js($blank),
            setView(v) { this.view = v; try { localStorage.setItem('branches-view', v) } catch (e) {} },
            create() { this.form = { ...this.blank }; $dispatch('open-drawer', 'branch-form') },
            edit(branch) { this.form = { ...branch }; $dispatch('open-drawer', 'branch-form') },
        }"
        x-init="@if ($reopen) $nextTick(() => $dispatch('open-drawer', 'branch-form')) @endif">

        <x-page-header title="Branches" subtitle="Shop locations. The 3-letter code is used as the invoice prefix (e.g. HYD-S-000001)."
            :breadcrumbs="['Settings' => null, 'Branches' => null]">
            <x-button icon="plus" @click="create()">Add Branch</x-button>
        </x-page-header>

        {{-- Stats --}}
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <x-stat-card label="Total branches" :value="$stats['total']" icon="building" />
            <x-stat-card label="Active branches" :value="$stats['active']" icon="check-circle" color="success" />
            <x-stat-card label="Branch staff" :value="$stats['staff']" icon="users" color="info" />
        </div>

        {{-- Toolbar --}}
        <form method="GET" class="card mb-6 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name, code or city…" class="form-control pl-10" aria-label="Search branches">
            </div>
            <select name="status" class="form-control sm:w-44" onchange="this.form.submit()" aria-label="Status">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            <div class="flex rounded-lg border border-line p-1" role="group" aria-label="View">
                <button type="button" @click="setView('cards')" class="flex h-8 w-9 items-center justify-center rounded-md transition" :class="view === 'cards' ? 'bg-primary text-white' : 'text-muted hover:text-heading'" aria-label="Card view"><x-icon name="layout-grid" size="w-4 h-4" /></button>
                <button type="button" @click="setView('table')" class="flex h-8 w-9 items-center justify-center rounded-md transition" :class="view === 'table' ? 'bg-primary text-white' : 'text-muted hover:text-heading'" aria-label="Table view"><x-icon name="list" size="w-4 h-4" /></button>
            </div>
        </form>

        @if ($branches->isEmpty())
            <x-card>
                <x-empty-state icon="building" title="No branches found" description="Try another search, or add your first branch.">
                    <x-button icon="plus" @click="create()">Add Branch</x-button>
                </x-empty-state>
            </x-card>
        @else
            {{-- Cards --}}
            <div x-show="view === 'cards'" class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($branches as $branch)
                    @php $payload = $branch->only(['id', 'name', 'code', 'phone', 'email', 'address', 'city', 'is_active']); @endphp
                    <div @class(['card card-hover flex flex-col overflow-hidden', 'opacity-70' => ! $branch->is_active])>
                        <div class="h-1.5" style="background: linear-gradient(90deg, {{ $branch->color }}, {{ $branch->color }}55)"></div>
                        <div class="flex-1 p-6">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-12 w-12 items-center justify-center rounded-xl text-sm font-bold text-white shadow-sm" style="background: {{ $branch->color }}">{{ $branch->code }}</span>
                                    <div class="min-w-0">
                                        <h3 class="truncate text-card-title text-heading">{{ $branch->name }}</h3>
                                        <p class="flex items-center gap-1 text-xs text-muted"><x-icon name="map-pin" size="w-3.5 h-3.5" /> {{ $branch->city }}</p>
                                    </div>
                                </div>
                                <x-dropdown width="w-44">
                                    <x-slot name="trigger">
                                        <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-subtle hover:text-heading" aria-label="Actions for {{ $branch->name }}"><x-icon name="more-horizontal" size="w-5 h-5" /></button>
                                    </x-slot>
                                    <x-slot name="content">
                                        <button type="button" class="dropdown-item" @click="edit(@js($payload))"><x-icon name="pencil" size="w-4 h-4" /> Edit</button>
                                        <form method="POST" action="{{ route('branches.destroy', $branch) }}" data-confirm="Delete {{ $branch->name }}?" data-confirm-text="Branches with staff or transactions cannot be deleted — deactivate them instead.">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger hover:!text-danger"><x-icon name="trash" size="w-4 h-4" /> Delete</button>
                                        </form>
                                    </x-slot>
                                </x-dropdown>
                            </div>

                            <dl class="mt-5 space-y-2.5 text-sm">
                                <div class="flex items-center gap-2.5 text-body"><x-icon name="phone" size="w-4 h-4 text-muted" /> {{ $branch->phone ?: '—' }}</div>
                                <div class="flex items-center gap-2.5 text-body"><x-icon name="mail" size="w-4 h-4 text-muted" /> <span class="truncate">{{ $branch->email ?: '—' }}</span></div>
                                <div class="flex items-start gap-2.5 text-body"><x-icon name="map-pin" size="w-4 h-4 mt-0.5 shrink-0 text-muted" /> <span class="line-clamp-2">{{ $branch->address ?: '—' }}</span></div>
                            </dl>
                        </div>
                        <div class="flex items-center justify-between border-t border-line bg-subtle/50 px-6 py-3.5">
                            <span class="flex items-center gap-2 text-sm text-body"><x-icon name="users" size="w-4 h-4 text-muted" /> <span class="font-semibold text-heading tabular">{{ $branch->users_count }}</span> staff</span>
                            <form method="POST" action="{{ route('branches.toggle', $branch) }}" class="flex items-center gap-2.5"
                                @if ($branch->is_active) data-confirm="Deactivate {{ $branch->name }}?" data-confirm-text="Staff of this branch will not be able to log in." data-confirm-button="Deactivate" @endif>
                                @csrf @method('PATCH')
                                <x-badge :color="$branch->is_active ? 'success' : 'slate'" dot>{{ $branch->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                <button type="submit" role="switch" aria-checked="{{ $branch->is_active ? 'true' : 'false' }}" aria-label="Toggle {{ $branch->name }}"
                                    @class(['relative h-6 w-11 rounded-full transition', 'bg-primary' => $branch->is_active, 'bg-line' => ! $branch->is_active])>
                                    <span @class(['absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition', 'translate-x-5' => $branch->is_active])></span>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Table --}}
            <div x-show="view === 'table'" x-cloak>
                <x-table>
                    <x-slot name="head">
                        <tr><th>Branch</th><th>Code</th><th>City</th><th>Phone</th><th class="text-right">Staff</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </x-slot>
                    @foreach ($branches as $branch)
                        @php $payload = $branch->only(['id', 'name', 'code', 'phone', 'email', 'address', 'city', 'is_active']); @endphp
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $branch->color }}"></span>
                                    <div><p class="font-semibold text-heading">{{ $branch->name }}</p><p class="text-xs text-muted">{{ $branch->email }}</p></div>
                                </div>
                            </td>
                            <td><x-badge color="primary">{{ $branch->code }}</x-badge></td>
                            <td>{{ $branch->city }}</td>
                            <td class="tabular">{{ $branch->phone ?: '—' }}</td>
                            <td class="text-right tabular font-semibold text-heading">{{ $branch->users_count }}</td>
                            <td><x-badge :color="$branch->is_active ? 'success' : 'slate'" dot>{{ $branch->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                            <td class="text-right">
                                <div class="flex justify-end">
                                    <x-dropdown width="w-48">
                                        <x-slot name="trigger">
                                            <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-subtle hover:text-heading" aria-label="Actions"><x-icon name="more-horizontal" size="w-5 h-5" /></button>
                                        </x-slot>
                                        <x-slot name="content">
                                            <button type="button" class="dropdown-item" @click="edit(@js($payload))"><x-icon name="pencil" size="w-4 h-4" /> Edit</button>
                                            <form method="POST" action="{{ route('branches.toggle', $branch) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="dropdown-item"><x-icon name="power" size="w-4 h-4" /> {{ $branch->is_active ? 'Deactivate' : 'Activate' }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('branches.destroy', $branch) }}" data-confirm="Delete {{ $branch->name }}?">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger hover:!text-danger"><x-icon name="trash" size="w-4 h-4" /> Delete</button>
                                            </form>
                                        </x-slot>
                                    </x-dropdown>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </div>
        @endif

        {{-- Create / edit drawer --}}
        <x-drawer name="branch-form" title-text="form.id ? 'Edit branch' : 'New branch'" subtitle="Branch details and invoice prefix">
            <form method="POST" :action="form.id ? @js(route('branches.update', '__ID__')).replace('__ID__', form.id) : @js(route('branches.store'))" class="flex flex-1 flex-col overflow-hidden">
                @csrf
                <input type="hidden" name="_method" :value="form.id ? 'PUT' : 'POST'">
                <input type="hidden" name="_form" value="branch">
                <input type="hidden" name="_id" :value="form.id">

                <div class="scrollbar-thin flex-1 space-y-5 overflow-y-auto px-6 py-6">
                    <x-input name="name" label="Branch name" placeholder="e.g. Hyderabad Main" x-model="form.name" required />
                    <div class="grid grid-cols-2 gap-4">
                        <x-input name="code" label="Code" placeholder="HYD" maxlength="3" x-model="form.code" @input="form.code = form.code.toUpperCase()" style="text-transform: uppercase" required help="3 letters, invoice prefix" />
                        <x-input name="city" label="City" placeholder="Hyderabad" x-model="form.city" required />
                    </div>
                    <x-input name="phone" label="Phone" icon="phone" placeholder="022-2781234" x-model="form.phone" />
                    <x-input name="email" type="email" label="Email" icon="mail" placeholder="branch@example.com" x-model="form.email" />
                    <x-input name="address" type="textarea" label="Address" placeholder="Shop no, street, area" x-model="form.address" />
                    <label class="flex cursor-pointer items-start justify-between gap-4 rounded-xl border border-line p-4">
                        <span>
                            <span class="block text-sm font-medium text-heading">Active</span>
                            <span class="block text-xs text-muted">Inactive branches are hidden from the switcher and their staff cannot log in.</span>
                        </span>
                        <span class="relative inline-flex shrink-0">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="peer sr-only" x-model="form.is_active">
                            <span class="h-6 w-11 rounded-full bg-line transition peer-checked:bg-primary peer-focus-visible:ring-[3px] peer-focus-visible:ring-primary/30"></span>
                            <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                </div>

                <div class="flex justify-end gap-2 border-t border-line bg-subtle/60 px-6 py-4">
                    <x-button variant="outline" @click="$dispatch('close-drawer', 'branch-form')">Cancel</x-button>
                    <x-button type="submit" icon="check" loading><span x-text="form.id ? 'Save changes' : 'Create branch'"></span></x-button>
                </div>
            </form>
        </x-drawer>
    </div>
</x-app-layout>
