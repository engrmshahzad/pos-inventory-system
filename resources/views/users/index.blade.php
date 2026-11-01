<x-app-layout title="Users">
    @php
        $actor = auth()->user();
        $roleColors = ['owner' => 'violet', 'manager' => 'info', 'cashier' => 'success'];
        $blank = ['id' => null, 'name' => '', 'email' => '', 'phone' => '', 'role' => $actor->isOwner() ? 'cashier' : 'cashier',
            'branch_id' => $actor->isOwner() ? (string) (currentBranchId() ?? '') : (string) $actor->branch_id, 'is_active' => true, 'avatar_url' => null];
        $reopen = $errors->any() && old('_form') === 'user';
        $initial = $reopen
            ? array_merge($blank, \Illuminate\Support\Arr::only(old(), ['name', 'email', 'phone', 'role', 'branch_id']), ['id' => old('_id') ?: null, 'is_active' => (bool) old('is_active')])
            : $blank;
    @endphp

    <div x-data="{
            form: @js($initial),
            blank: @js($blank),
            preview: null,
            create() { this.form = { ...this.blank }; this.preview = null; this.$refs.avatar.value = ''; $dispatch('open-drawer', 'user-form') },
            edit(user) { this.form = { ...user }; this.preview = user.avatar_url; this.$refs.avatar.value = ''; $dispatch('open-drawer', 'user-form') },
            pick(e) { const f = e.target.files[0]; if (f) { this.preview = URL.createObjectURL(f); this.form.remove_avatar = false } },
        }"
        x-init="@if ($reopen) $nextTick(() => $dispatch('open-drawer', 'user-form')) @endif">

        <x-page-header title="Users" :subtitle="$actor->isOwner() ? 'Everyone who can sign in, across all branches.' : 'Staff of '.$actor->branch?->name.'. You can manage cashiers.'"
            :breadcrumbs="['Settings' => null, 'Users' => null]">
            @can('roles.manage')
                <x-button :href="route('roles.index')" variant="outline" icon="shield">Roles</x-button>
            @endcan
            @can('create', \App\Models\User::class)
                <x-button icon="user-plus" @click="create()">{{ $actor->isOwner() ? 'Add User' : 'Add Cashier' }}</x-button>
            @endcan
        </x-page-header>

        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Total users" :value="$stats['total']" icon="users" />
            <x-stat-card label="Active" :value="$stats['active']" icon="check-circle" color="success" />
            <x-stat-card label="Managers" :value="$stats['managers']" icon="shield" color="info" />
            <x-stat-card label="Cashiers" :value="$stats['cashiers']" icon="shopping-cart" color="warning" />
        </div>

        <x-table :paginator="$users">
            <x-slot name="toolbar">
                <form method="GET" class="flex w-full flex-col gap-3 lg:flex-row lg:items-center">
                    <div class="relative flex-1 lg:max-w-sm">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
                        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name, email, phone…" class="form-control pl-10" aria-label="Search users">
                    </div>
                    <div class="flex flex-wrap gap-3">
                        @if ($actor->isOwner())
                            <select name="role" class="form-control w-auto min-w-[140px]" onchange="this.form.submit()" aria-label="Role">
                                <option value="">All roles</option>
                                @foreach ($roles as $role)<option value="{{ $role }}" @selected(request('role') === $role)>{{ ucfirst($role) }}</option>@endforeach
                            </select>
                            <select name="branch" class="form-control w-auto min-w-[160px]" onchange="this.form.submit()" aria-label="Branch">
                                <option value="">All branches</option>
                                @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch') == $b->id)>{{ $b->name }}</option>@endforeach
                            </select>
                        @endif
                        <select name="status" class="form-control w-auto min-w-[130px]" onchange="this.form.submit()" aria-label="Status">
                            <option value="">Any status</option>
                            <option value="active" @selected(request('status') === 'active')>Active</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                        </select>
                        @if (request()->hasAny(['search', 'role', 'branch', 'status']))
                            <x-button :href="route('users.index')" variant="ghost" icon="x">Clear</x-button>
                        @endif
                    </div>
                </form>
            </x-slot>

            <x-slot name="head">
                <tr>
                    <th class="w-10"><input type="checkbox" class="form-checkbox" aria-label="Select all" @change="$root.querySelectorAll('[data-row-check]').forEach(c => c.checked = $event.target.checked)"></th>
                    <th>User</th><th>Role</th><th>Branch</th><th>Phone</th><th>Status</th><th>Last login</th><th class="text-right">Actions</th>
                </tr>
            </x-slot>

            @foreach ($users as $user)
                @php
                    $payload = [
                        'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
                        'role' => $user->role_name, 'branch_id' => (string) $user->branch_id, 'is_active' => $user->is_active, 'avatar_url' => $user->avatar_url,
                    ];
                    $canEdit = $actor->can('update', $user);
                    $canDelete = $actor->can('delete', $user);
                @endphp
                <tr>
                    <td><input type="checkbox" class="form-checkbox" data-row-check value="{{ $user->id }}" aria-label="Select {{ $user->name }}"></td>
                    <td>
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$user" size="md" />
                            <div class="min-w-0">
                                <p class="font-semibold text-heading">{{ $user->name }} @if ($user->id === $actor->id)<span class="text-xs font-normal text-muted">(you)</span>@endif</p>
                                <p class="truncate text-xs text-muted">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td><x-badge :color="$roleColors[$user->role_name] ?? 'slate'" class="capitalize">{{ $user->role_name ?? '—' }}</x-badge></td>
                    <td>
                        @if ($user->branch)
                            <span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full" style="background: {{ $user->branch->color }}"></span>{{ $user->branch->name }}</span>
                        @else
                            <span class="text-muted">All branches</span>
                        @endif
                    </td>
                    <td class="tabular">{{ $user->phone ?: '—' }}</td>
                    <td><x-badge :color="$user->is_active ? 'success' : 'slate'" dot>{{ $user->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                    <td class="whitespace-nowrap">
                        @if ($user->last_login_at)
                            <p class="text-heading">{{ $user->last_login_at->diffForHumans() }}</p>
                            <p class="text-xs text-muted tabular">{{ $user->last_login_ip }}</p>
                        @else
                            <span class="text-muted">Never</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if ($canEdit || $canDelete)
                            <div class="flex justify-end">
                                <x-dropdown width="w-48">
                                    <x-slot name="trigger">
                                        <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-subtle hover:text-heading" aria-label="Actions for {{ $user->name }}"><x-icon name="more-horizontal" size="w-5 h-5" /></button>
                                    </x-slot>
                                    <x-slot name="content">
                                        @if ($canEdit)
                                            <button type="button" class="dropdown-item" @click="edit(@js($payload))"><x-icon name="pencil" size="w-4 h-4" /> Edit</button>
                                            @if ($user->id !== $actor->id)
                                                <form method="POST" action="{{ route('users.toggle', $user) }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="dropdown-item"><x-icon name="power" size="w-4 h-4" /> {{ $user->is_active ? 'Deactivate' : 'Activate' }}</button>
                                                </form>
                                            @endif
                                        @endif
                                        @if ($canDelete)
                                            <form method="POST" action="{{ route('users.destroy', $user) }}" data-confirm="Delete {{ $user->name }}?" data-confirm-text="They will no longer be able to sign in.">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger hover:!text-danger"><x-icon name="trash" size="w-4 h-4" /> Delete</button>
                                            </form>
                                        @endif
                                    </x-slot>
                                </x-dropdown>
                            </div>
                        @else
                            <span class="text-xs text-muted">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach

            @if ($users->isEmpty())
                <x-slot name="empty">
                    <x-empty-state icon="users" title="No users found" description="Try a different search or filter." />
                </x-slot>
            @endif
        </x-table>

        {{-- Create / edit drawer --}}
        <x-drawer name="user-form" title-text="form.id ? 'Edit user' : 'New user'" subtitle="Account, role and branch">
            <form method="POST" enctype="multipart/form-data" class="flex flex-1 flex-col overflow-hidden"
                :action="form.id ? @js(route('users.update', '__ID__')).replace('__ID__', form.id) : @js(route('users.store'))">
                @csrf
                <input type="hidden" name="_method" :value="form.id ? 'PUT' : 'POST'">
                <input type="hidden" name="_form" value="user">
                <input type="hidden" name="_id" :value="form.id">
                <input type="hidden" name="remove_avatar" :value="form.remove_avatar ? 1 : 0">

                <div class="scrollbar-thin flex-1 space-y-5 overflow-y-auto px-6 py-6">
                    {{-- Avatar --}}
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <template x-if="preview"><img :src="preview" alt="" class="h-16 w-16 rounded-full object-cover ring-2 ring-line"></template>
                            <template x-if="!preview">
                                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary"><x-icon name="camera" size="w-6 h-6" /></span>
                            </template>
                        </div>
                        <div>
                            <div class="flex gap-2">
                                <x-button size="sm" variant="outline" icon="upload" @click="$refs.avatar.click()">Upload photo</x-button>
                                <x-button size="sm" variant="ghost-danger" x-show="preview" @click="preview = null; form.remove_avatar = true; $refs.avatar.value = ''">Remove</x-button>
                            </div>
                            <p class="mt-1.5 text-xs text-muted">JPG, PNG or WEBP, max 2 MB.</p>
                            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="hidden" x-ref="avatar" @change="pick($event)">
                            @error('avatar')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <x-input name="name" label="Full name" icon="user" placeholder="e.g. Bilal Memon" x-model="form.name" required />
                    <x-input name="email" type="email" label="Email" icon="mail" placeholder="name@example.com" x-model="form.email" required />
                    <x-input name="phone" label="Phone" icon="phone" placeholder="0300-1234567" x-model="form.phone" />

                    @if ($actor->isOwner())
                        <div class="grid grid-cols-2 gap-4">
                            <x-select name="role" label="Role" :options="collect($roles)->mapWithKeys(fn ($r) => [$r => ucfirst($r)])->all()" x-model="form.role" required />
                            <div x-show="form.role !== 'owner'">
                                <x-select name="branch_id" label="Branch" placeholder="Select branch" :options="$branches->pluck('name', 'id')->all()" x-model="form.branch_id" ::required="form.role !== 'owner'" />
                            </div>
                        </div>
                        <p x-show="form.role === 'owner'" class="form-help -mt-2">Owners have access to all branches.</p>
                    @else
                        <div class="flex items-center gap-3 rounded-xl border border-line bg-subtle p-3.5 text-sm">
                            <x-icon name="info" size="w-4 h-4 text-info" />
                            <span>New staff will be a <strong class="text-heading">cashier</strong> at <strong class="text-heading">{{ $actor->branch?->name }}</strong>.</span>
                        </div>
                    @endif

                    <div class="rounded-xl border border-line p-4">
                        <p class="mb-3 text-sm font-medium text-heading" x-text="form.id ? 'Change password (optional)' : 'Password'"></p>
                        <div class="space-y-4">
                            <x-input name="password" type="password" icon="lock" placeholder="At least 8 characters" autocomplete="new-password" ::required="!form.id" />
                            <x-input name="password_confirmation" type="password" icon="lock" placeholder="Repeat password" autocomplete="new-password" ::required="!form.id" />
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-start justify-between gap-4 rounded-xl border border-line p-4">
                        <span>
                            <span class="block text-sm font-medium text-heading">Active</span>
                            <span class="block text-xs text-muted">Inactive users cannot sign in.</span>
                        </span>
                        <span class="relative inline-flex shrink-0">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="peer sr-only" x-model="form.is_active">
                            <span class="h-6 w-11 rounded-full bg-line transition peer-checked:bg-primary"></span>
                            <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                        </span>
                    </label>
                    @error('is_active')<p class="form-error -mt-3">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-2 border-t border-line bg-subtle/60 px-6 py-4">
                    <x-button variant="outline" @click="$dispatch('close-drawer', 'user-form')">Cancel</x-button>
                    <x-button type="submit" icon="check" loading><span x-text="form.id ? 'Save changes' : 'Create user'"></span></x-button>
                </div>
            </form>
        </x-drawer>
    </div>
</x-app-layout>
