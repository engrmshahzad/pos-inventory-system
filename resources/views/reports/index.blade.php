<x-app-layout title="Reports">
    <x-page-header title="Reports" :subtitle="'Every report has date presets, branch filter, Excel / PDF export and print.'.($currentBranch ? ' Showing '.$currentBranch->name.' by default.' : '')" :breadcrumbs="['Reports' => null]">
        @role('owner')<x-button :href="route('activity.index')" variant="outline" icon="clock">Activity log</x-button>@endrole
    </x-page-header>

    @php $groupIcons = ['Sales' => 'receipt', 'Profit' => 'trending-up', 'Purchases' => 'truck', 'Stock' => 'boxes', 'Money' => 'wallet']; @endphp
    <div class="space-y-8">
        @foreach ($groups as $group => $reports)
            <section>
                <h2 class="section-label mb-3 flex items-center gap-2"><x-icon :name="$groupIcons[$group] ?? 'bar-chart'" size="w-4 h-4" /> {{ $group }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($reports as $report)
                        <a href="{{ route('reports.show', $report->key) }}" class="card card-hover group flex items-start gap-4 p-5">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-white dark:text-primary-300"><x-icon :name="$report->icon" size="w-5 h-5" /></span>
                            <span class="min-w-0">
                                <span class="block font-semibold text-heading">{{ $report->title }}</span>
                                <span class="mt-0.5 block text-xs text-muted">{{ $report->description }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-app-layout>
