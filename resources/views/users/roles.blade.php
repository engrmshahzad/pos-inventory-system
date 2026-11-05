<x-app-layout title="Roles & Permissions">
    @php
        $roleColors = ['owner' => 'violet', 'manager' => 'info', 'cashier' => 'success'];
        $blank = ['id' => null, 'name' => '', 'base_type' => 'cashier', 'permissions' => [], 'builtIn' => false];
        $reopen = $errors->any() && old('_form') === 'role';
        $initial = $reopen
            ? ['id' => old('_id') ?: null, 'name' => old('name', ''), 'base_type' => old('base_type', 'cashier'), 'permissions' => array_values((array) old('permissions', [])), 'builtIn' => (bool) old('_builtin')]
            : $blank;
    @endphp

    <div x-data="{
            form: @js($initial), blank: @js($blank),
            create() { this.form = { ...this.blank, permissions: [] }; $dispatch('open-drawer', 'role-form') },
            edit(r) { this.form = { ...r, permissions: [...r.permissions] }; $dispatch('open-drawer', 'role-form') },
            toggleGroup(perms) {
                const all = perms.every(p => this.form.permissions.includes(p));
                this.form.permissions = all ? this.form.permissions.filter(p => !perms.includes(p)) : [...new Set([...this.form.permissions, ...perms])];
            },
        }" x-init="@if ($reopen) $nextTick(() => $dispatch('open-drawer', 'role-form')) @endif">

        <x-page-header title="Roles & Permissions" subtitle="What each role can do. Custom roles see branch data like a manager or a cashier."
            :breadcrumbs="['Settings' => null, 'Users' => route('users.index'), 'Roles' => null]">
            <x-button :href="route('users.index')" variant="outline" icon="users">Manage users</x-button>
            <x-button icon="plus" @click="create()">Add role</x-button>
        </x-page-header>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($roles as $role)
                @php
                    $payload = ['id' => $role->id, 'name' => $role->name, 'base_type' => $role->base_type, 'builtIn' => $role->isBuiltIn(),
                        'permissions' => $role->permissions->pluck('name')->values()];
                @endphp
                <div class="card flex flex-col p-5">
                    <div class="flex items-center justify-between gap-2">
                        <x-badge :color="$roleColors[$role->name] ?? 'slate'" class="capitalize">{{ $role->name }}</x-badge>
                        <span class="text-xs text-muted">{{ $role->users_count }} {{ \Illuminate\Support\Str::plural('user', $role->users_count) }}</span>
                    </div>
                    <p class="mt-3 text-kpi tabular text-heading">{{ $role->permissions->count() }}</p>
                    <p class="text-xs text-muted">
                        permissions ·
                        @if ($role->name === 'owner') all branches, always everything
                        @elseif ($role->isBuiltIn()) built-in
                        @else {{ $baseTypes[$role->base_type][0] ?? $role->base_type }}
                        @endif
                    </p>
                    @if ($role->name !== 'owner')
                        <div class="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
                            <x-button size="sm" variant="outline" icon="pencil" @click="edit({{ \Illuminate\Support\Js::from($payload) }})">Edit</x-button>
                            @if ($role->isBuiltIn())
                                <form method="POST" action="{{ route('roles.reset', $role) }}" data-confirm="Reset {{ $role->name }} to default permissions?" data-confirm-text="Changes made here will be undone." data-confirm-danger="false">
                                    @csrf
                                    <x-button type="submit" size="sm" variant="ghost" icon="rotate-ccw">Reset</x-button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('roles.destroy', $role) }}" data-confirm="Delete role {{ $role->name }}?">
                                    @csrf @method('DELETE')
                                    <x-button type="submit" size="sm" variant="ghost-danger" icon="trash">Delete</x-button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <x-table>
            <x-slot name="head">
                <tr>
                    <th>Permission</th>
                    @foreach ($roles as $role)<th class="text-center capitalize">{{ $role->name }}</th>@endforeach
                </tr>
            </x-slot>
            @foreach ($groups as $group => $permissions)
                <tr class="!bg-subtle/60"><td colspan="{{ $roles->count() + 1 }}" class="!py-2 section-label">{{ $group }}</td></tr>
                @foreach ($permissions as $permission)
                    <tr>
                        <td class="font-mono text-xs text-heading">
                            {{ $permission }}
                            @if (in_array($permission, $ownerOnly))<x-icon name="lock" size="w-3 h-3" class="ml-1 inline text-muted" />@endif
                        </td>
                        @foreach ($roles as $role)
                            <td class="text-center">
                                @if ($granted[$role->name]->has($permission))
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-success/10 text-emerald-600"><x-icon name="check" size="w-3.5 h-3.5" /></span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
        </x-table>
        <p class="mt-3 text-xs text-muted"><x-icon name="lock" size="w-3 h-3" class="inline" /> Owner-only: cannot be given to any other role.</p>

        <x-drawer name="role-form" title-text="form.id ? `Edit role: ${form.name}` : 'New role'" subtitle="Name, data access and permissions">
            <form id="role-form" method="POST" class="flex-1 space-y-6 overflow-y-auto px-6 py-5"
                :action="form.id ? @js(route('roles.update', '__ID__')).replace('__ID__', form.id) : @js(route('roles.store'))">
                @csrf
                <input type="hidden" name="_method" :value="form.id ? 'PUT' : 'POST'">
                <input type="hidden" name="_form" value="role">
                <input type="hidden" name="_id" :value="form.id">
                <input type="hidden" name="_builtin" :value="form.builtIn ? 1 : 0">

                <div>
                    <x-input name="name" label="Role name" placeholder="e.g. Senior Cashier" x-model="form.name" ::readonly="form.builtIn" required />
                    <p x-show="form.builtIn" class="form-help">Built-in roles keep their name; only permissions can change.</p>
                </div>

                <div>
                    <span class="form-label">Data access</span>
                    <div class="grid gap-2">
                        @foreach ($baseTypes as $type => [$label, $hint])
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-line px-3 py-2.5 text-sm has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                                :class="form.builtIn && 'pointer-events-none opacity-60'">
                                <input type="radio" name="base_type" value="{{ $type }}" x-model="form.base_type" class="mt-0.5 h-4 w-4 border-line text-primary">
                                <span><b class="text-heading">{{ $label }}</b><span class="block text-xs text-muted">{{ $hint }}</span></span>
                            </label>
                        @endforeach
                    </div>
                    @error('base_type')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <span class="form-label !mb-0">Permissions</span>
                        <span class="text-xs text-muted"><span x-text="form.permissions.length"></span> selected</span>
                    </div>
                    @error('permissions.*')<p class="form-error mb-2">{{ $message }}</p>@enderror
                    <div class="space-y-4">
                        @foreach ($groups as $group => $permissions)
                            @php $assignable = array_values(array_diff($permissions, $ownerOnly)); @endphp
                            <div class="rounded-xl border border-line p-3">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="section-label">{{ $group }}</span>
                                    @if ($assignable)
                                        <button type="button" class="text-xs font-semibold text-primary" @click="toggleGroup(@js($assignable))"
                                            x-text="@js($assignable).every(p => form.permissions.includes(p)) ? 'Clear' : 'Select all'"></button>
                                    @endif
                                </div>
                                <div class="grid gap-1.5 sm:grid-cols-2">
                                    @foreach ($permissions as $permission)
                                        @if (in_array($permission, $ownerOnly))
                                            <span class="flex items-center gap-2 text-xs text-muted" title="Owner only"><x-icon name="lock" size="w-3.5 h-3.5" /> <span class="font-mono">{{ $permission }}</span></span>
                                        @else
                                            <label class="flex cursor-pointer items-center gap-2 text-xs text-heading">
                                                <input type="checkbox" name="permissions[]" value="{{ $permission }}" x-model="form.permissions" class="h-4 w-4 rounded border-line text-primary">
                                                <span class="font-mono">{{ $permission }}</span>
                                            </label>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </form>
            <div class="flex justify-end gap-2 border-t border-line bg-subtle/60 px-6 py-4">
                <x-button variant="outline" @click="$dispatch('close-drawer', 'role-form')">Cancel</x-button>
                <x-button type="submit" form="role-form" icon="check" loading><span x-text="form.id ? 'Save changes' : 'Create role'"></span></x-button>
            </div>
        </x-drawer>
    </div>
</x-app-layout>
