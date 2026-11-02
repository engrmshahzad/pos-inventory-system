<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ? $title.' · ' : '' }}{{ settings('business_name', config('app.name')) }}</title>
@if ($favicon = settings('business_favicon'))
    <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($favicon) }}">
@else
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='6' fill='%234F46E5'/%3E%3Cpath d='M6 9l6-3.5L18 9v6l-6 3.5L6 15z' fill='none' stroke='white' stroke-width='1.8' stroke-linejoin='round'/%3E%3C/svg%3E">
@endif

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

{{-- Apply saved theme before paint to avoid a light flash in dark mode. --}}
<script>
    try {
        const saved = localStorage.getItem('theme');
        if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    } catch (e) {}
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
