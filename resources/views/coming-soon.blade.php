<x-app-layout :title="$module">
    <x-page-header :title="$module" :breadcrumbs="[$module => null]" subtitle="This module is part of an upcoming build phase." />

    <x-card>
        <x-empty-state :icon="$icon" :title="$module.' is coming soon'"
            :description="'We are building this module'.($phase ? ' in Phase '.$phase : '').'. Menu access is already wired to your role\'s permissions.'">
            @if ($phase)<x-badge color="primary" icon="sparkles">Phase {{ $phase }}</x-badge>@endif
            <x-button :href="route('dashboard')" variant="outline" icon="layout-dashboard">Back to dashboard</x-button>
        </x-empty-state>
    </x-card>
</x-app-layout>
