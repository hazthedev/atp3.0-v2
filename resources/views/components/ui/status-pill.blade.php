@props([
    'tone' => 'neutral', // ok | due-soon | overdue | info | neutral
])
@php
    $tones = [
        'ok'       => 'bg-success-100 text-success-700',
        'due-soon' => 'bg-warning-100 text-warning-700',
        'overdue'  => 'bg-danger-100 text-danger-700',
        'info'     => 'bg-accent-50 text-accent-700',
        'neutral'  => 'bg-neutral-100 text-neutral-600',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] font-medium leading-none', $tones[$tone] ?? $tones['neutral']]) }}>
    {{ $slot }}
</span>
