<?php

use App\Services\Airworthiness\AirworthinessReviewService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $registration;
    public ?string $asOf = null;

    public function mount(string $registration): void
    {
        $this->registration = $registration;
    }

    #[Computed]
    public function review(): array
    {
        return app(AirworthinessReviewService::class)->getReview($this->registration, $this->asOf);
    }
};
?>

@php
    $verdictTone = [
        \App\Services\Airworthiness\AirworthinessReviewService::AIRWORTHY => 'ok',
        \App\Services\Airworthiness\AirworthinessReviewService::NOT_AIRWORTHY => 'overdue',
        \App\Services\Airworthiness\AirworthinessReviewService::REVIEW_INCOMPLETE => 'due-soon',
    ];
    $resultTone = ['PASS' => 'ok', 'FAIL' => 'overdue', 'NOT EVALUATED' => 'neutral'];
    $labels = [
        'work_packages' => 'Work Packages', 'amp' => 'Maintenance Programme',
        'technical_publications' => 'Technical Publications', 'defects' => 'Defects', 'configuration' => 'Configuration',
    ];
@endphp

<div>
    <x-ui.page-header title="Airworthiness Review" :subtitle="$registration">
        <x-slot:actions>
            <x-ui.input type="date" wire:model.live="asOf" class="!w-40" />
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mt-4 flex items-center gap-3 rounded-lg border border-neutral-200 bg-white p-4">
        <span class="text-xs uppercase tracking-wide text-neutral-400">Verdict</span>
        <x-ui.status-pill :tone="$verdictTone[$this->review['verdict']] ?? 'neutral'">
            <span class="text-[13px]">{{ $this->review['verdict'] }}</span>
        </x-ui.status-pill>
    </div>

    <div class="mt-4">
        <x-ui.table>
            <x-slot:head>
                <th class="px-3 py-2 font-medium">Criterion</th>
                <th class="px-3 py-2 font-medium">Result</th>
                <th class="px-3 py-2 font-medium">Detail</th>
            </x-slot:head>
            @foreach ($this->review['criteria'] as $key => $c)
                <tr class="hover:bg-neutral-50" wire:key="crit-{{ $key }}">
                    <td class="px-3 py-2 text-neutral-700">{{ $labels[$key] ?? $key }}</td>
                    <td class="px-3 py-2"><x-ui.status-pill :tone="$resultTone[$c['result']] ?? 'neutral'">{{ $c['result'] }}</x-ui.status-pill></td>
                    <td class="px-3 py-2 text-sm text-neutral-500">{{ $c['reason'] ?? (($c['outstanding'] ?? 0) === 0 ? 'OK' : '') }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    </div>
</div>
