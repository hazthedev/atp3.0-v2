<?php

use App\Models\Flight;
use App\Models\FunctionalLocation;
use App\Services\Flight\FlightCounterHandover;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $flId = null;
    public string $scheduledDate = '';
    public ?string $hoursAfter = null;
    public ?string $cycleAfter = null;
    public ?string $flash = null;

    #[Computed]
    public function aircraft()
    {
        return FunctionalLocation::orderBy('registration')->get();
    }

    #[Computed]
    public function recent()
    {
        return Flight::with('functionalLocation')->orderByDesc('id')->limit(10)->get();
    }

    public function save(FlightCounterHandover $handover): void
    {
        $data = $this->validate([
            'flId' => ['required', 'exists:functional_locations,id'],
            'scheduledDate' => ['required', 'date'],
            'hoursAfter' => ['nullable', 'numeric', 'min:0'],
            'cycleAfter' => ['nullable', 'integer', 'min:0'],
        ]);

        $flight = Flight::create([
            'functional_location_id' => $data['flId'],
            'scheduled_date' => $data['scheduledDate'],
            'status' => 'Completed',
            'ac_hours_after_minutes' => $data['hoursAfter'],
            'ac_cycle_after' => $data['cycleAfter'],
        ]);

        // absolute-readings handover -> counter engine -> penalties/projections
        $handover->handover($flight);

        $this->reset(['flId', 'scheduledDate', 'hoursAfter', 'cycleAfter']);
        unset($this->recent);
        $this->flash = "Flight recorded for {$flight->functionalLocation?->registration}; counters updated.";
    }
};
?>

<div>
    <x-ui.page-header title="Flight Recording" subtitle="Record after-flight readings" />

    <div class="mt-4 grid gap-4 lg:grid-cols-[20rem_1fr]">
        <x-ui.card title="New flight">
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Aircraft</label>
                    <x-ui.select wire:model="flId">
                        <option value="">Select…</option>
                        @foreach ($this->aircraft as $ac)
                            <option value="{{ $ac->id }}">{{ $ac->registration }}</option>
                        @endforeach
                    </x-ui.select>
                    @error('flId') <p class="mt-1 text-xs text-danger-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Date</label>
                    <x-ui.input type="date" wire:model="scheduledDate" />
                    @error('scheduledDate') <p class="mt-1 text-xs text-danger-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Hours after (min)</label>
                    <x-ui.input type="number" step="0.01" wire:model="hoursAfter" data-numeric />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Cycles after</label>
                    <x-ui.input type="number" wire:model="cycleAfter" data-numeric />
                </div>
                <div class="flex justify-end pt-1">
                    <x-ui.button variant="primary" size="sm" wire:click="save">Record flight</x-ui.button>
                </div>
                @if ($flash)
                    <p class="rounded-md bg-success-100 px-2.5 py-1.5 text-xs text-success-700">{{ $flash }}</p>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card title="Recent flights" :padded="false">
            <x-ui.table>
                <x-slot:head>
                    <th class="px-3 py-2 font-medium">Aircraft</th>
                    <th class="px-3 py-2 font-medium">Date</th>
                    <th class="px-3 py-2 font-medium text-right">Hours after</th>
                    <th class="px-3 py-2 font-medium text-right">Cycles after</th>
                </x-slot:head>
                @forelse ($this->recent as $f)
                    <tr class="hover:bg-neutral-50" wire:key="flight-{{ $f->id }}">
                        <td class="px-3 py-2 text-neutral-700">{{ $f->functionalLocation?->registration }}</td>
                        <td class="px-3 py-2 text-neutral-600">{{ $f->scheduled_date?->format('d M Y') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $f->ac_hours_after_minutes !== null ? rtrim(rtrim((string) $f->ac_hours_after_minutes, '0'), '.') : '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $f->ac_cycle_after ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-sm text-neutral-400">No flights recorded.</td></tr>
                @endforelse
            </x-ui.table>
        </x-ui.card>
    </div>
</div>
