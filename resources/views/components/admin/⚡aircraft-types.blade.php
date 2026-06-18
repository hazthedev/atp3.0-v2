<?php

use App\Models\AircraftType;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $code = '';
    public string $name = '';

    #[Computed]
    public function types()
    {
        return AircraftType::orderBy('code')->get();
    }

    public function new(): void
    {
        $this->reset(['editingId', 'code', 'name']);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $type = AircraftType::findOrFail($id);
        $this->editingId = $type->id;
        $this->code = $type->code;
        $this->name = $type->name;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'code', 'name', 'showForm']);
    }

    public function save(): void
    {
        $data = $this->validate([
            'code' => ['required', 'string', 'max:50', 'unique:aircraft_types,code'.($this->editingId ? ','.$this->editingId : '')],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($this->editingId) {
            AircraftType::findOrFail($this->editingId)->update($data);
        } else {
            AircraftType::create($data);
        }

        $this->reset(['editingId', 'code', 'name', 'showForm']);
        unset($this->types);
    }

    public function delete(int $id): void
    {
        AircraftType::findOrFail($id)->delete();
        unset($this->types);
    }
};
?>

<div>
    <x-ui.page-header title="Aircraft Types" subtitle="Reference data · Administration">
        <x-slot:actions>
            @unless ($showForm)
                <x-ui.button variant="primary" size="sm" wire:click="new">New Type</x-ui.button>
            @endunless
        </x-slot:actions>
    </x-ui.page-header>

    @if ($showForm)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-neutral-900">{{ $editingId ? 'Edit' : 'New' }} aircraft type</h2>
            <div class="mt-3 grid max-w-md gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Code</label>
                    <x-ui.input wire:model="code" placeholder="AW139" />
                    @error('code') <p class="mt-1 text-xs text-danger-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Name</label>
                    <x-ui.input wire:model="name" placeholder="Leonardo AW139" />
                    @error('name') <p class="mt-1 text-xs text-danger-700">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <x-ui.button variant="ghost" size="sm" wire:click="cancel">Cancel</x-ui.button>
                    <x-ui.button variant="primary" size="sm" wire:click="save">Save</x-ui.button>
                </div>
            </div>
        </div>
    @endif

    <div class="mt-4">
        <x-ui.table>
            <x-slot:head>
                <th class="px-3 py-2 font-medium">Code</th>
                <th class="px-3 py-2 font-medium">Name</th>
                <th class="px-3 py-2 font-medium text-right">Actions</th>
            </x-slot:head>
            @forelse ($this->types as $type)
                <tr class="hover:bg-neutral-50" wire:key="type-{{ $type->id }}">
                    <td class="px-3 py-2 font-mono text-[13px] text-neutral-700">{{ $type->code }}</td>
                    <td class="px-3 py-2 text-neutral-700">{{ $type->name }}</td>
                    <td class="px-3 py-2 text-right">
                        <button wire:click="edit({{ $type->id }})" class="text-[13px] text-accent-700 hover:underline">Edit</button>
                        <span class="text-neutral-300">·</span>
                        <button wire:click="delete({{ $type->id }})" wire:confirm="Delete this type?" class="text-[13px] text-danger-700 hover:underline">Delete</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-3 py-6 text-center text-sm text-neutral-400">No aircraft types.</td></tr>
            @endforelse
        </x-ui.table>
    </div>
</div>
