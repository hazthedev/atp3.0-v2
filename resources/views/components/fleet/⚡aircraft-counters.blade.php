<?php

use App\Models\FunctionalLocation;
use App\Models\FunctionalLocationCounter;
use App\Services\Counters\FunctionalLocationCounterUpdater;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $registration;
    public int $flId;
    public bool $editing = false;
    public array $inputs = [];   // counter_ref_id => entered value
    public ?string $flash = null;
    public array $violations = [];

    public function mount(string $registration): void
    {
        $fl = FunctionalLocation::where('registration', $registration)->firstOrFail();
        $this->registration = $fl->registration;
        $this->flId = $fl->id;
    }

    #[Computed]
    public function counters()
    {
        return FunctionalLocationCounter::query()
            ->where('functional_location_id', $this->flId)
            ->with('counterRef')
            ->get()
            ->sortBy(fn ($c) => $c->counterRef?->counter_code)
            ->values();
    }

    public function enterEdit(): void
    {
        $this->editing = true;
        $this->flash = null;
        $this->violations = [];
        $this->inputs = $this->counters
            ->mapWithKeys(fn ($c) => [$c->counter_ref_id => $c->value_dec !== null ? (string) (float) $c->value_dec : ''])
            ->all();
    }

    public function cancel(): void
    {
        $this->editing = false;
        $this->inputs = [];
    }

    public function save(FunctionalLocationCounterUpdater $updater): void
    {
        $fl = FunctionalLocation::findOrFail($this->flId);

        $rows = [];
        foreach ($this->inputs as $refId => $val) {
            if ($val === '' || $val === null) {
                continue;
            }
            $rows[] = ['counter_ref_id' => (int) $refId, 'value_dec' => (float) $val, 'propagate' => true];
        }

        $result = $updater->applyRows($fl, $rows, ['source_ref' => 'ui:aircraft-counters']);

        $this->violations = $result->directionViolations;
        $this->editing = $this->violations !== [];   // stay in edit if any row was rejected
        unset($this->counters);   // bust the computed cache
        $this->flash = $this->violations === []
            ? 'Counters updated.'
            : count($this->violations).' reading(s) rejected (wrong direction).';
    }
};
?>

<div>
    <x-ui.page-header :title="'Aircraft Counters'" :subtitle="$registration">
        <x-slot:actions>
            @if ($editing)
                <x-ui.button variant="ghost" size="sm" wire:click="cancel">Cancel</x-ui.button>
                <x-ui.button variant="primary" size="sm" wire:click="save">Save</x-ui.button>
            @else
                <x-ui.button variant="primary" size="sm" wire:click="enterEdit">Update Counter</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($flash)
        <div class="mt-3 rounded-md border border-neutral-200 bg-neutral-50 px-3 py-2 text-sm text-neutral-700">{{ $flash }}</div>
    @endif

    <div class="mt-4">
        <x-ui.table>
            <x-slot:head>
                <th class="px-3 py-2 font-medium">Counter</th>
                <th class="px-3 py-2 font-medium text-right">Value</th>
                <th class="px-3 py-2 font-medium text-right">Remaining</th>
                <th class="px-3 py-2 font-medium">State</th>
            </x-slot:head>
            @forelse ($this->counters as $counter)
                <tr class="hover:bg-neutral-50" wire:key="ctr-{{ $counter->counter_ref_id }}">
                    <td class="px-3 py-2 font-mono text-[13px] text-neutral-700">{{ $counter->counterRef?->counter_code }}</td>
                    <td class="px-3 py-2 text-right">
                        @if ($editing)
                            <x-ui.input type="number" step="0.0001" class="!h-7 text-right"
                                        wire:model="inputs.{{ $counter->counter_ref_id }}" />
                        @else
                            <span class="tabular-nums">{{ $counter->value_dec !== null ? rtrim(rtrim((string) $counter->value_dec, '0'), '.') : '—' }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums text-neutral-500">{{ $counter->remaining !== null ? rtrim(rtrim((string) $counter->remaining, '0'), '.') : '—' }}</td>
                    <td class="px-3 py-2">
                        @if ($counter->is_used)
                            <x-ui.status-pill tone="ok">Used</x-ui.status-pill>
                        @else
                            <x-ui.status-pill tone="neutral">Not Init.</x-ui.status-pill>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-6 text-center text-sm text-neutral-400">No counters assigned.</td></tr>
            @endforelse
        </x-ui.table>
    </div>
</div>
