@props([
    'head' => null,   // a slot of <th> cells
])
{{-- Dense table shell: hairline borders, zebra-free, tabular numerals (from base layer). --}}
<div {{ $attributes->class('overflow-hidden rounded-lg border border-neutral-200') }}>
    <table class="w-full text-sm">
        @isset($head)
            <thead>
                <tr class="border-b border-neutral-200 bg-neutral-50 text-left text-xs text-neutral-500">
                    {{ $head }}
                </tr>
            </thead>
        @endisset
        <tbody class="divide-y divide-neutral-100">
            {{ $slot }}
        </tbody>
    </table>
</div>
