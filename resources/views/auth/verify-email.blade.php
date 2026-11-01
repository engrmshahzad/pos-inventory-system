<x-guest-layout title="Verify email">
    <h1 class="text-2xl font-bold tracking-tight text-heading">Verify your email</h1>
    <p class="mt-1.5 text-sm text-muted">Please click the link we emailed you. Didn't get it? We can send another.</p>

    @if (session('status') == 'verification-link-sent')
        <div class="mt-6 rounded-lg bg-success/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">A new verification link has been sent.</div>
    @endif

    <div class="mt-8 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button type="submit" loading>Resend email</x-button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button type="submit" variant="ghost">Log out</x-button>
        </form>
    </div>
</x-guest-layout>
