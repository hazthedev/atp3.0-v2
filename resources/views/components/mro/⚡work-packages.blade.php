<?php

use App\Models\WorkPackage;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $statusFilter = '';

    #[Computed]
    public function packages()
    {
        return WorkPackage::query()
            ->with('functionalLocation')
            ->withCount('tasks')
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->get();
    }
};
?>

@php
    $statusTone = ['Planned' => 'info', 'In Progress' => 'due-soon', 'Completed' => 'ok', 'Cancelled' => 'neutral'];
@endphp

<div>
    <x-ui.page-header title="Work Packages" :subtitle="$this->packages->count().' total'">
        <x-slot:actions>
            <x-ui.select wire:model.live="statusFilter" class="!w-40">
                <option value="">All statuses</option>
                <option value="Planned">Planned</option>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
            </x-ui.select>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mt-4">
        <x-ui.table>
            <x-slot:head>
                <th class="px-3 py-2 font-medium">Code</th>
                <th class="px-3 py-2 font-medium">Aircraft</th>
                <th class="px-3 py-2 font-medium">Type</th>
                <th class="px-3 py-2 font-medium text-right">Tasks</th>
                <th class="px-3 py-2 font-medium">Status</th>
            </x-slot:head>
            @forelse ($this->packages as $wp)
                <tr class="hover:bg-neutral-50" wire:key="wp-{{ $wp->id }}">
                    <td class="px-3 py-2 font-mono text-[13px] text-neutral-700">{{ $wp->code }}</td>
                    <td class="px-3 py-2 text-neutral-700">{{ $wp->functionalLocation?->registration }}</td>
                    <td class="px-3 py-2 text-neutral-500">{{ $wp->work_package_type }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ $wp->tasks_count }}</td>
                    <td class="px-3 py-2"><x-ui.status-pill :tone="$statusTone[$wp->status] ?? 'neutral'">{{ $wp->status }}</x-ui.status-pill></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-sm text-neutral-400">No work packages.</td></tr>
            @endforelse
        </x-ui.table>
    </div>
</div>
