@props([
    'type' => 'text',
])
<input
    type="{{ $type }}"
    {{ $attributes->class('h-8 w-full rounded-md border border-neutral-200 bg-white px-2.5 text-sm text-neutral-900 placeholder:text-neutral-400 transition-colors hover:border-neutral-300 focus-visible:border-accent-500 focus-visible:outline-none disabled:bg-neutral-50 disabled:text-neutral-500') }}
/>
