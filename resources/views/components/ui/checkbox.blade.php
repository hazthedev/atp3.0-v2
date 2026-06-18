@props([
    'label' => null,
])
<label class="inline-flex items-center gap-2 text-sm text-neutral-700 select-none">
    <input type="checkbox" {{ $attributes->class('h-4 w-4 rounded border-neutral-300 text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500') }} />
    @if ($label){{ $label }}@endif
    {{ $slot }}
</label>
