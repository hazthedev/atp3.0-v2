@props([
    'title',
    'subtitle' => null,
])
<div {{ $attributes->class('flex items-center justify-between border-b border-neutral-200 pb-3') }}>
    <div>
        <h1 class="text-base font-semibold tracking-tight text-neutral-900">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-0.5 text-xs text-neutral-500">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
