<?php

use App\Models\FunctionalLocation;
use App\Services\Airworthiness\AirworthinessReviewService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function rows()
    {
        $service = app(AirworthinessReviewService::class);

        return FunctionalLocation::orderBy('registration')->get()
            ->map(fn ($fl) => [
                'registration' => $fl->registration,
                'type' => $fl->aircraftType?->code,
                'verdict' => $service->getReview($fl->registration)['verdict'],
            ]);
    }

    #[Computed]
    public function tally(): array
    {
        return $this->rows->countBy('verdict')->all();
    }
};
?>

@php
    $tone = [
        \App\Services\Airworthiness\AirworthinessReviewService::AIRWORTHY => 'ok',
        \App\Services\Airworthiness\AirworthinessReviewService::NOT_AIRWORTHY => 'overdue',
        \App\Services\Airworthiness\AirworthinessReviewService::REVIEW_INCOMPLETE => 'due-soon',
    ];
@endphp

<div>
    <x-ui.page-header title="Fleet Status Report" subtitle="Airworthiness across the fleet" />

    <div class="mt-4 grid gap-3 sm:grid-cols-3">
        @foreach ([\App\Services\Airworthiness\AirworthinessReviewService::AIRWORTHY, \App\Services\Airworthiness\AirworthinessReviewService::NOT_AIRWORTHY, \App\Services\Airworthiness\AirworthinessReviewService::REVIEW_INCOMPLETE] as $v)
            <x-ui.card>
                <div class="text-2xl font-semibold tabular-nums text-neutral-900">{{ $this->tally[$v] ?? 0 }}</div>
                <div class="mt-1"><x-ui.status-pill :tone="$tone[$v]">{{ $v }}</x-ui.status-pill></div>
            </x-ui.card>
        @endforeach
    </div>

    <div class="mt-4">
        <x-ui.table>
            <x-slot:head>
                <th class="px-3 py-2 font-medium">Registration</th>
                <th class="px-3 py-2 font-medium">Type</th>
                <th class="px-3 py-2 font-medium">Verdict</th>
            </x-slot:head>
            @forelse ($this->rows as $row)
                <tr class="hover:bg-neutral-50" wire:key="rep-{{ $row['registration'] }}">
                    <td class="px-3 py-2 font-medium text-neutral-900">{{ $row['registration'] }}</td>
                    <td class="px-3 py-2 text-neutral-600">{{ $row['type'] }}</td>
                    <td class="px-3 py-2"><x-ui.status-pill :tone="$tone[$row['verdict']] ?? 'neutral'">{{ $row['verdict'] }}</x-ui.status-pill></td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-3 py-6 text-center text-sm text-neutral-400">No aircraft.</td></tr>
            @endforelse
        </x-ui.table>
    </div>
</div>
