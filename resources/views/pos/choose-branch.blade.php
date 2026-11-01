<x-app-layout title="POS">
    <x-page-header title="Point of Sale" subtitle="A sale always belongs to one branch." :breadcrumbs="['Sales' => null, 'POS' => null]" />
    <x-card>
        <x-empty-state icon="store" title="Choose a branch to start selling" description="You are viewing All Branches. Pick the branch whose stock this till sells from.">
            <form method="POST" action="{{ route('branch.switch') }}" class="flex flex-wrap justify-center gap-2">
                @csrf
                @foreach ($switchableBranches as $b)
                    <button name="branch" value="{{ $b->id }}" class="inline-flex h-btn items-center gap-2 rounded-lg border border-line bg-card px-4 text-sm font-semibold text-heading transition hover:border-primary/40 hover:bg-subtle">
                        <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $b->color }}"></span>{{ $b->name }}
                    </button>
                @endforeach
            </form>
        </x-empty-state>
    </x-card>
</x-app-layout>
