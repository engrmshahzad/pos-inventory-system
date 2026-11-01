<x-guest-layout title="Forgot password">
    <span class="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary"><x-icon name="key" size="w-6 h-6" /></span>
    <h1 class="text-2xl font-bold tracking-tight text-heading">Forgot your password?</h1>
    <p class="mt-1.5 text-sm text-muted">Enter your email and we'll send you a link to choose a new one.</p>

    @if (session('status'))
        <div class="mt-6 flex items-start gap-2.5 rounded-lg bg-success/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">
            <x-icon name="check-circle" size="w-4 h-4 mt-0.5 shrink-0" /> {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf
        <x-input name="email" type="email" label="Email address" icon="mail" placeholder="you@example.com" required autofocus />
        <x-button type="submit" size="lg" class="w-full" loading>Email reset link</x-button>
    </form>

    <a href="{{ route('login') }}" class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:text-primary-hover">
        <x-icon name="chevron-left" size="w-4 h-4" /> Back to sign in
    </a>
</x-guest-layout>
