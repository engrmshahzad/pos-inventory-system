<x-app-layout title="Notifications">
    @php $tints = ['primary' => 'bg-primary/10 text-primary', 'success' => 'bg-success/10 text-emerald-600', 'warning' => 'bg-warning/10 text-amber-600', 'danger' => 'bg-danger/10 text-red-600', 'info' => 'bg-info/10 text-sky-600']; @endphp
    <x-page-header title="Notifications" :subtitle="$unread ? $unread.' unread' : 'You are all caught up.'" :breadcrumbs="['Notifications' => null]">
        @if ($unread)
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<x-button type="submit" variant="outline" icon="check">Mark all read</x-button></form>
        @endif
    </x-page-header>

    <div class="mb-4 flex gap-6 border-b border-line">
        @foreach (['' => 'All', 'unread' => 'Unread'] as $k => $l)
            <a href="{{ route('notifications.index', array_filter(['filter' => $k])) }}" @class(['-mb-px border-b-2 pb-3 text-sm font-semibold', 'border-primary text-primary' => request('filter', '') === $k, 'border-transparent text-muted hover:text-heading' => request('filter', '') !== $k])>{{ $l }}</a>
        @endforeach
    </div>

    <x-card padding="p-0">
        <ul class="divide-y divide-line">
            @forelse ($notifications as $n)
                <li>
                    <a href="{{ route('notifications.open', $n->id) }}" @class(['flex gap-4 px-6 py-4 transition hover:bg-subtle', 'bg-primary/[.03]' => ! $n->read_at])>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $tints[$n->data['color'] ?? 'primary'] ?? $tints['primary'] }}"><x-icon :name="$n->data['icon'] ?? 'bell'" size="w-5 h-5" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2 font-semibold text-heading">{{ $n->data['title'] ?? 'Notification' }} @unless ($n->read_at)<span class="h-2 w-2 rounded-full bg-primary"></span>@endunless</span>
                            <span class="block text-sm text-body">{{ $n->data['message'] ?? '' }}</span>
                        </span>
                        <span class="shrink-0 text-right text-xs text-muted">{{ fmtDate($n->created_at, true) }}<span class="block">{{ $n->created_at->diffForHumans() }}</span></span>
                    </a>
                </li>
            @empty
                <li><x-empty-state icon="bell" title="No notifications" description="Transfers, approvals, low stock and exports will show here." /></li>
            @endforelse
        </ul>
        @if ($notifications->hasPages())<div class="border-t border-line px-4 py-3">{{ $notifications->links() }}</div>@endif
    </x-card>
</x-app-layout>
