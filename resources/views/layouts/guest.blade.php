<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-screen bg-card">
    <div class="flex min-h-screen">
        {{-- Brand panel --}}
        <div class="relative hidden w-1/2 overflow-hidden bg-gradient-to-br from-primary-600 via-primary-700 to-violet-700 lg:flex lg:flex-col xl:w-[55%]">
            <div class="hero-pattern absolute inset-0 opacity-60"></div>
            <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-violet-400/30 blur-3xl"></div>
            <div class="absolute -bottom-32 -left-20 h-[28rem] w-[28rem] rounded-full bg-indigo-300/20 blur-3xl"></div>

            <div class="relative flex flex-1 flex-col justify-between p-12 xl:p-16">
                <div class="flex items-center gap-3">
                    <x-logo class="!bg-white/15 !from-white/20 !to-white/10 ring-1 ring-white/25 backdrop-blur" />
                    <span class="text-lg font-bold tracking-tight text-white">{{ config('app.name') }}</span>
                </div>

                {{-- Illustration: floating dashboard cards built from CSS --}}
                <div class="relative mx-auto my-10 w-full max-w-md" aria-hidden="true">
                    <div class="rounded-2xl border border-white/15 bg-white/10 p-5 shadow-2xl backdrop-blur-md">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-medium text-indigo-100/80">Today's Sales · All Branches</p>
                                <p class="mt-1 text-3xl font-bold text-white tabular">Rs 4,82,350</p>
                            </div>
                            <span class="rounded-full bg-emerald-400/20 px-2.5 py-1 text-xs font-semibold text-emerald-200">▲ 18.2%</span>
                        </div>
                        <svg viewBox="0 0 300 80" class="mt-4 h-20 w-full">
                            <defs><linearGradient id="g" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".45"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient></defs>
                            <path d="M0 60 C30 55 45 30 75 35 S120 55 150 40 S200 10 225 18 S270 30 300 8 L300 80 L0 80Z" fill="url(#g)"/>
                            <path d="M0 60 C30 55 45 30 75 35 S120 55 150 40 S200 10 225 18 S270 30 300 8" fill="none" stroke="#fff" stroke-width="2.5"/>
                        </svg>
                    </div>
                    <div class="absolute -bottom-10 -left-8 w-48 rounded-xl border border-white/15 bg-white/15 p-4 shadow-xl backdrop-blur-md">
                        <p class="text-[11px] text-indigo-100/80">Branches online</p>
                        <div class="mt-2 flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-emerald-300"></span><span class="text-xs font-semibold text-white">HYD</span>
                            <span class="h-2 w-2 rounded-full bg-amber-300"></span><span class="text-xs font-semibold text-white">KHI</span>
                            <span class="h-2 w-2 rounded-full bg-sky-300"></span><span class="text-xs font-semibold text-white">SUK</span>
                        </div>
                    </div>
                    <div class="absolute -right-6 -top-8 flex items-center gap-3 rounded-xl border border-white/15 bg-white/15 px-4 py-3 shadow-xl backdrop-blur-md">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/20 text-white"><x-icon name="receipt" size="w-4 h-4" /></span>
                        <div><p class="text-[11px] text-indigo-100/80">Last invoice</p><p class="text-xs font-semibold text-white">HYD-S-000143</p></div>
                    </div>
                </div>

                <div>
                    <h2 class="max-w-md text-3xl font-bold leading-tight text-white xl:text-4xl">Run every branch from one screen.</h2>
                    <p class="mt-3 max-w-md text-[15px] leading-relaxed text-indigo-100/85">Fast POS billing, live per-branch stock, transfers and udhaar khata — built for growing retail businesses.</p>
                    <div class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm text-indigo-100/80">
                        <span class="flex items-center gap-2"><x-icon name="check-circle" size="w-4 h-4 text-emerald-300" /> Multi-branch stock</span>
                        <span class="flex items-center gap-2"><x-icon name="check-circle" size="w-4 h-4 text-emerald-300" /> Keyboard-fast POS</span>
                        <span class="flex items-center gap-2"><x-icon name="check-circle" size="w-4 h-4 text-emerald-300" /> Role-based access</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Form panel --}}
        <div class="relative flex flex-1 flex-col bg-card">
            <div class="absolute right-4 top-4" x-data>
                <button type="button" @click="$store.theme.toggle()" class="flex h-10 w-10 items-center justify-center rounded-lg text-body hover:bg-subtle" aria-label="Toggle dark mode">
                    <x-icon name="moon" size="w-5 h-5" x-show="!$store.theme.dark" />
                    <x-icon name="sun" size="w-5 h-5" x-show="$store.theme.dark" x-cloak />
                </button>
            </div>
            <div class="flex flex-1 items-center justify-center px-6 py-12 sm:px-12">
                <div class="w-full max-w-[400px]">
                    <div class="mb-8 flex items-center gap-3 lg:hidden">
                        <x-logo />
                        <span class="text-lg font-bold text-heading">{{ config('app.name') }}</span>
                    </div>
                    {{ $slot }}
                </div>
            </div>
            <p class="pb-6 text-center text-xs text-muted">© {{ now()->year }} {{ settings('business_name', config('app.name')) }}</p>
        </div>
    </div>
    <x-toasts />
</body>
</html>
