@props([
    'title' => null,
    'padded' => true,
])
<div {{ $attributes->class('rounded-lg border border-neutral-200 bg-white shadow-sm') }}>
    @if ($title)
        <div class="border-b border-neutral-100 px-4 py-2.5">
            <h2 class="text-sm font-semibold tracking-tight text-neutral-900">{{ $title }}</h2>
        </div>
    @endif
    <div class="{{ $padded ? 'p-4' : '' }}">
        {{ $slot }}
    </div>
</div>
