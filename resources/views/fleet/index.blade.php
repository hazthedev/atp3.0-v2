<x-layouts.shell title="Fleet" active="fleet">
    <x-ui.page-header title="Fleet" :subtitle="$aircraft->count().' aircraft'" />

    <div class="mt-4">
        <x-ui.table>
            <x-slot:head>
                <th class="px-3 py-2 font-medium">Registration</th>
                <th class="px-3 py-2 font-medium">Code</th>
                <th class="px-3 py-2 font-medium">Type</th>
                <th class="px-3 py-2 font-medium"></th>
            </x-slot:head>
            @forelse ($aircraft as $ac)
                <tr class="hover:bg-neutral-50">
                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $ac->registration }}</td>
                    <td class="px-3 py-2 font-mono text-[13px] text-neutral-600">{{ $ac->code }}</td>
                    <td class="px-3 py-2 text-neutral-600">{{ $ac->aircraftType?->code }}</td>
                    <td class="px-3 py-2 text-right">
                        <a href="{{ route('fleet.aircraft.counters', $ac->registration) }}" class="text-[13px] font-medium text-accent-700 hover:underline">Counters →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-6 text-center text-sm text-neutral-400">No aircraft. Run the demo seeder.</td></tr>
            @endforelse
        </x-ui.table>
    </div>
</x-layouts.shell>
