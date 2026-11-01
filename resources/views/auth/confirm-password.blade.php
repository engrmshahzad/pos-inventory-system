<x-guest-layout title="Confirm password">
    <h1 class="text-2xl font-bold tracking-tight text-heading">Confirm your password</h1>
    <p class="mt-1.5 text-sm text-muted">This is a secure area. Please confirm your password to continue.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-8 space-y-5">
        @csrf
        <x-input name="password" type="password" label="Password" icon="lock" required autocomplete="current-password" />
        <x-button type="submit" size="lg" class="w-full" loading>Confirm</x-button>
    </form>
</x-guest-layout>
