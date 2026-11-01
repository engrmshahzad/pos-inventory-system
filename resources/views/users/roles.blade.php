<x-app-layout title="Roles & Permissions">
    @php $roleColors = ['owner' => 'violet', 'manager' => 'info', 'cashier' => 'success']; @endphp

    <x-page-header title="Roles & Permissions" subtitle="What each role can do. Permissions are defined in app/Support/Permissions.php."
        :breadcrumbs="['Settings' => null, 'Users' => route('users.index'), 'Roles' => null]">
        <x-button :href="route('users.index')" variant="outline" icon="users">Manage users</x-button>
    </x-page-header>

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        @foreach ($roles as $role)
            <div class="card p-5">
                <div class="flex items-center justify-between">
                    <x-badge :color="$roleColors[$role->name] ?? 'slate'" class="capitalize">{{ $role->name }}</x-badge>
                    <span class="text-xs text-muted">{{ $role->users_count }} {{ \Illuminate\Support\Str::plural('user', $role->users_count) }}</span>
                </div>
                <p class="mt-3 text-kpi tabular text-heading">{{ $role->permissions->count() }}</p>
                <p class="text-xs text-muted">permissions granted</p>
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
                    <td class="font-mono text-xs text-heading">{{ $permission }}</td>
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
</x-app-layout>
