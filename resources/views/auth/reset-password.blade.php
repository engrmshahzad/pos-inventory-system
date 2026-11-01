<x-guest-layout title="Reset password">
    <span class="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary"><x-icon name="lock" size="w-6 h-6" /></span>
    <h1 class="text-2xl font-bold tracking-tight text-heading">Set a new password</h1>
    <p class="mt-1.5 text-sm text-muted">Choose a strong password of at least 8 characters.</p>

    <form method="POST" action="{{ route('password.store') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-input name="email" type="email" label="Email address" icon="mail" :value="$request->email" required autocomplete="username" />
        <x-input name="password" type="password" label="New password" icon="lock" required autofocus autocomplete="new-password" />
        <x-input name="password_confirmation" type="password" label="Confirm password" icon="lock" required autocomplete="new-password" />
        <x-button type="submit" size="lg" class="w-full" loading>Reset password</x-button>
    </form>
</x-guest-layout>
