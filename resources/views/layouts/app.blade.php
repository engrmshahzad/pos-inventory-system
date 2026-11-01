<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
    {{-- Restore the collapsed sidebar state before paint so the layout does not jump. --}}
    <script>
        try { if (localStorage.getItem('sidebar-collapsed') === '1') document.documentElement.classList.add('sidebar-collapsed'); } catch (e) {}
    </script>
</head>
<body class="min-h-screen">
    @php
        $menu = \App\Support\Menu::for(auth()->user());
        $paletteItems = collect($menu)->flatMap(fn ($items, $group) => collect($items)->map(fn ($i) => [
            'label' => $i['label'], 'url' => $i['url'], 'group' => $group, 'icon' => $i['icon'],
        ]))->push(['label' => 'My Profile', 'url' => route('profile.edit'), 'group' => 'Account', 'icon' => 'user'])->values();
    @endphp

    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[90] focus:rounded-lg focus:bg-primary focus:px-4 focus:py-2 focus:text-white">Skip to content</a>

    <div x-data class="min-h-screen">
        @include('layouts.partials.sidebar', ['menu' => $menu])

        <div class="flex min-h-screen min-w-0 flex-col transition-[padding] duration-200 lg:pl-sidebar [.sidebar-collapsed_&]:lg:pl-rail"
            :class="$store.sidebar.collapsed ? 'lg:!pl-rail' : 'lg:!pl-sidebar'">
            @include('layouts.partials.header')

            <main id="main" class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                {{ $slot }}
            </main>

            <footer class="px-4 pb-6 text-xs text-muted sm:px-6 lg:px-8">
                © {{ now()->year }} {{ settings('business_name', config('app.name')) }} · Inventory & POS
            </footer>
        </div>
    </div>

    @include('layouts.partials.command-palette', ['items' => $paletteItems])
    <x-toasts />
    @stack('scripts')
</body>
</html>
