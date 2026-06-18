<?php

use App\Models\MaintenanceProgram;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function programs()
    {
        return MaintenanceProgram::query()
            ->withCount('items')
            ->orderBy('code')
            ->get();
    }
};
?>

@php $statusTone = ['Approved' => 'ok', 'Draft' => 'neutral', 'Superseded' => 'due-soon']; @endphp

<div>
    <x-ui.page-header title="Maintenance Programmes" subtitle="Technical Data · AMP library" />

    <div class="mt-4">
        <x-ui.table>
            <x-slot:head>
                <th class="px-3 py-2 font-medium">Code</th>
                <th class="px-3 py-2 font-medium">Title</th>
                <th class="px-3 py-2 font-medium">Revision</th>
                <th class="px-3 py-2 font-medium text-right">Items</th>
                <th class="px-3 py-2 font-medium">Status</th>
            </x-slot:head>
            @forelse ($this->programs as $program)
                <tr class="hover:bg-neutral-50" wire:key="amp-{{ $program->id }}">
                    <td class="px-3 py-2 font-mono text-[13px] text-neutral-700">{{ $program->code }}</td>
                    <td class="px-3 py-2 text-neutral-700">{{ $program->title }}</td>
                    <td class="px-3 py-2 text-neutral-500">{{ $program->revision ?? '—' }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ $program->items_count }}</td>
                    <td class="px-3 py-2"><x-ui.status-pill :tone="$statusTone[$program->status] ?? 'neutral'">{{ $program->status }}</x-ui.status-pill></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-sm text-neutral-400">No maintenance programmes.</td></tr>
            @endforelse
        </x-ui.table>
    </div>
</div>
