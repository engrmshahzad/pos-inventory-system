<x-app-layout title="Activity Log">
    <x-page-header title="Activity log" subtitle="Who did what, when, at which branch and from which IP." :breadcrumbs="['Settings' => null, 'Activity log' => null]" />

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6 lg:items-center">
        <div class="relative sm:col-span-2">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-muted"><x-icon name="search" size="w-4 h-4" /></span>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search description…" class="form-control pl-10" aria-label="Search">
        </div>
        <select name="user" class="form-control" aria-label="User"><option value="">All users</option>@foreach ($users as $id => $name)<option value="{{ $id }}" @selected(request('user') == $id)>{{ $name }}</option>@endforeach</select>
        <select name="action" class="form-control" aria-label="Action"><option value="">All actions</option>@foreach ($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ str_replace('_', ' ', ucfirst($a)) }}</option>@endforeach</select>
        <select name="branch" class="form-control" aria-label="Branch"><option value="">All branches</option>@foreach ($branches as $id => $name)<option value="{{ $id }}" @selected(request('branch') == $id)>{{ $name }}</option>@endforeach</select>
        <div class="flex gap-2">
            <input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="From">
            <x-button type="submit" icon="filter" size="icon" aria-label="Apply" />
        </div>
    </form>

    <x-table :paginator="$logs">
        <x-slot name="head"><tr><th>When</th><th>User</th><th>Action</th><th>Description</th><th>Branch</th><th>IP</th><th>Changes</th></tr></x-slot>
        @foreach ($logs as $log)
            @php $color = ['created' => 'success', 'updated' => 'info', 'deleted' => 'danger', 'login' => 'primary', 'logout' => 'slate', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'danger'][$log->action] ?? 'slate'; @endphp
            <tr x-data="{ open: false }">
                <td class="whitespace-nowrap">{{ fmtDate($log->created_at, true) }}<span class="block text-xs text-muted">{{ $log->created_at->diffForHumans() }}</span></td>
                <td>{{ $log->user?->name ?? 'System' }}</td>
                <td><x-badge :color="$color">{{ str_replace('_', ' ', $log->action) }}</x-badge></td>
                <td class="max-w-md text-heading">{{ $log->description }}</td>
                <td>@if ($log->branch)<x-badge color="slate">{{ $log->branch->code }}</x-badge>@else<span class="text-muted">—</span>@endif</td>
                <td class="tabular text-xs text-muted">{{ $log->ip_address ?? '—' }}</td>
                <td>
                    @if (! empty($log->properties['new']))
                        <button type="button" @click="open = !open" class="text-xs font-semibold text-primary">{{ count($log->properties['new']) }} field(s)</button>
                        <div x-show="open" x-cloak class="mt-2 space-y-1 text-xs">
                            @foreach ($log->properties['new'] as $field => $new)
                                <div><span class="font-mono text-muted">{{ $field }}:</span> <span class="text-danger line-through">{{ is_scalar($log->properties['old'][$field] ?? null) ? \Illuminate\Support\Str::limit((string) $log->properties['old'][$field], 40) : '—' }}</span> → <span class="text-emerald-600">{{ is_scalar($new) ? \Illuminate\Support\Str::limit((string) $new, 40) : json_encode($new) }}</span></div>
                            @endforeach
                        </div>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
            </tr>
        @endforeach
        @if ($logs->isEmpty())
            <x-slot name="empty"><x-empty-state icon="clock" title="No activity found" /></x-slot>
        @endif
    </x-table>
</x-app-layout>
