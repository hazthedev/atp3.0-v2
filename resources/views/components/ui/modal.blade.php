@props([
    'title' => null,
])
{{-- Self-contained Alpine modal. Pass the opener in the `trigger` slot, body in default slot. --}}
<div x-data="{ open: false }" @keydown.escape.window="open = false">
    @isset($trigger)
        <div @click="open = true">{{ $trigger }}</div>
    @endisset

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-neutral-950/30" @click="open = false"></div>
            <div role="dialog" aria-modal="true" x-show="open"
                 x-transition.opacity x-trap.inert.noscroll="open"
                 {{ $attributes->class('relative w-full max-w-lg rounded-lg border border-neutral-200 bg-white shadow-md') }}>
                @if ($title)
                    <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-2.5">
                        <h2 class="text-sm font-semibold tracking-tight text-neutral-900">{{ $title }}</h2>
                        <button type="button" @click="open = false" aria-label="Close"
                                class="text-neutral-400 transition-colors hover:text-neutral-700">&times;</button>
                    </div>
                @endif
                <div class="p-4">{{ $slot }}</div>
            </div>
        </div>
    </template>
</div>
