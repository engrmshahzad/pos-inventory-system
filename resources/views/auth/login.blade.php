<x-guest-layout title="Sign in">
    <h1 class="text-2xl font-bold tracking-tight text-heading">Welcome back 👋</h1>
    <p class="mt-1.5 text-sm text-muted">Sign in to your account to continue.</p>

    @if (session('status'))
        <div class="mt-6 flex items-start gap-2.5 rounded-lg bg-success/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300">
            <x-icon name="check-circle" size="w-4 h-4 mt-0.5 shrink-0" /> {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf
        <x-input name="email" type="email" label="Email address" icon="mail" placeholder="you@example.com" required autofocus autocomplete="username" />

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label for="password" class="text-[13px] font-medium text-heading">Password <span class="text-danger">*</span></label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-xs font-semibold text-primary hover:text-primary-hover">Forgot password?</a>
                @endif
            </div>
            <x-input name="password" type="password" icon="lock" placeholder="••••••••" required autocomplete="current-password" />
        </div>

        <label class="flex items-center gap-2.5 text-sm text-body">
            <input type="checkbox" name="remember" class="form-checkbox"> Keep me signed in
        </label>

        <x-button type="submit" size="lg" class="w-full" loading icon-right="arrow-right">Sign in</x-button>
    </form>

    @if (app()->isLocal())
        <div class="mt-8 rounded-xl border border-dashed border-line bg-subtle p-4" x-data>
            <p class="section-label mb-2.5">Demo logins · password: <span class="normal-case text-heading">password</span></p>
            <div class="grid grid-cols-2 gap-1.5 text-xs">
                @foreach (['owner@pos.test' => 'Owner', 'manager.hyd@pos.test' => 'Manager HYD', 'cashier.hyd@pos.test' => 'Cashier HYD', 'manager.khi@pos.test' => 'Manager KHI'] as $email => $label)
                    <button type="button" class="rounded-lg border border-line bg-card px-2.5 py-2 text-left transition hover:border-primary/40 hover:text-primary"
                        @click="document.getElementById('email').value = '{{ $email }}'; document.getElementById('password').value = 'password'">
                        <span class="block font-semibold text-heading">{{ $label }}</span>
                        <span class="block truncate text-muted">{{ $email }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</x-guest-layout>
