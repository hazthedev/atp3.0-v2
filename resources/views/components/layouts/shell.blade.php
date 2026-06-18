@props([
    'title' => null,
    'active' => null,   // current L1 key
])
@php
    $nav = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard'],
        ['key' => 'administration', 'label' => 'Administration', 'route' => 'stub'],
        ['key' => 'business-partners', 'label' => 'Business Partners', 'route' => 'stub'],
        ['key' => 'inventory', 'label' => 'Inventory', 'route' => 'stub'],
        ['key' => 'hr', 'label' => 'Human Resources', 'route' => 'stub'],
        ['key' => 'technical-data', 'label' => 'Technical Data', 'route' => 'stub'],
        ['key' => 'fleet', 'label' => 'Fleet', 'route' => 'fleet.index'],
        ['key' => 'flight', 'label' => 'Flight Recording', 'route' => 'stub'],
        ['key' => 'mro', 'label' => 'MRO', 'route' => 'mro.work-packages'],
        ['key' => 'reports', 'label' => 'Reports', 'route' => 'stub'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-neutral-50 text-neutral-900 antialiased">
    <div class="flex h-full">
        <aside class="flex w-56 shrink-0 flex-col border-r border-neutral-200 bg-white">
            <div class="flex h-12 items-center border-b border-neutral-100 px-4">
                <span class="text-sm font-semibold tracking-tight">ATP <span class="text-accent-600">3.0</span></span>
            </div>
            <nav class="flex-1 space-y-0.5 overflow-y-auto p-2">
                @foreach ($nav as $item)
                    @php $is = $active === $item['key']; @endphp
                    <a href="{{ $item['route'] === 'stub' ? route('stub', $item['key']) : route($item['route']) }}"
                       @class([
                           'block rounded-md px-2.5 py-1.5 text-[13px] transition-colors',
                           'bg-accent-50 font-medium text-accent-700' => $is,
                           'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' => ! $is,
                       ])>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            <div class="border-t border-neutral-100 p-3 text-[11px] text-neutral-400">v2 · {{ \Illuminate\Foundation\Application::VERSION }}</div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <main class="flex-1 overflow-y-auto">
                <div class="mx-auto max-w-6xl px-6 py-6">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</body>
</html>
