@props([
    'variant' => 'secondary', // primary | secondary | ghost | danger
    'size' => 'md',           // sm | md
    'type' => 'button',
])
@php
    $base = 'inline-flex items-center justify-center gap-1.5 font-medium rounded-md border transition-colors focus-visible:outline-none disabled:opacity-50 disabled:pointer-events-none';
    $sizes = [
        'sm' => 'h-7 px-2.5 text-xs',
        'md' => 'h-8 px-3 text-sm',
    ];
    $variants = [
        'primary'   => 'bg-accent-500 border-accent-500 text-white hover:bg-accent-600',
        'secondary' => 'bg-white border-neutral-200 text-neutral-700 hover:bg-neutral-50',
        'ghost'     => 'bg-transparent border-transparent text-neutral-600 hover:bg-neutral-100',
        'danger'    => 'bg-white border-danger-500/40 text-danger-700 hover:bg-danger-100',
    ];
@endphp
<button type="{{ $type }}" {{ $attributes->class([$base, $sizes[$size] ?? $sizes['md'], $variants[$variant] ?? $variants['secondary']]) }}>
    {{ $slot }}
</button>
