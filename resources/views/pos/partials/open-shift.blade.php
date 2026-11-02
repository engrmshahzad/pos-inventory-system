{{-- Blocks the POS until the user opens a shift. --}}
<div class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Open shift">
    <form method="POST" action="{{ route('register.open') }}" class="w-full max-w-md overflow-hidden rounded-2xl border border-line bg-card shadow-card-hover"
        x-data @keydown.stop>
        @csrf
        <div class="hero-pattern bg-gradient-to-br from-primary-600 to-violet-600 px-6 py-6 text-white">
            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/20"><x-icon name="wallet" size="w-6 h-6" /></span>
            <h2 class="mt-4 text-xl font-bold text-white">Open your shift</h2>
            <p class="mt-1 text-sm text-indigo-100">{{ $branch->name }} · {{ fmtDate(now(), true) }}</p>
        </div>
        <div class="space-y-4 p-6">
            <x-input name="opening_cash" type="number" step="0.01" min="0" label="Opening cash in drawer" icon="banknote" value="0" required autofocus
                help="Count the notes and coins in the drawer before the first sale." />
            <x-input name="opening_note" label="Note" placeholder="Optional" />
        </div>
        <div class="flex justify-between gap-2 border-t border-line bg-subtle/60 px-6 py-4">
            <x-button :href="route('dashboard')" variant="outline">Back</x-button>
            <x-button type="submit" icon="check" loading>Open shift</x-button>
        </div>
    </form>
</div>
