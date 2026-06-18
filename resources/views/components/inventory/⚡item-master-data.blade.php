<?php

use App\Models\Item;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $code = '';
    public string $description = '';
    public string $search = '';

    #[Computed]
    public function items()
    {
        return Item::query()
            ->when($this->search !== '', fn ($q) => $q->where('code', 'like', "%{$this->search}%")->orWhere('description', 'like', "%{$this->search}%"))
            ->orderBy('code')
            ->get();
    }

    public function new(): void
    {
        $this->reset(['editingId', 'code', 'description']);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $item = Item::findOrFail($id);
        $this->editingId = $item->id;
        $this->code = $item->code;
        $this->description = (string) $item->description;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'code', 'description', 'showForm']);
    }

    public function save(): void
    {
        $data = $this->validate([
            'code' => ['required', 'string', 'max:100', 'unique:items,code'.($this->editingId ? ','.$this->editingId : '')],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->editingId) {
            Item::findOrFail($this->editingId)->update($data);
        } else {
            Item::create($data);
        }

        $this->reset(['editingId', 'code', 'description', 'showForm']);
        unset($this->items);
    }

    public function delete(int $id): void
    {
        Item::findOrFail($id)->delete();
        unset($this->items);
    }
};
?>

<div>
    <x-ui.page-header title="Item Master Data" subtitle="Inventory · part master">
        <x-slot:actions>
            <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Search…" class="!w-48" />
            @unless ($showForm)
                <x-ui.button variant="primary" size="sm" wire:click="new">New Item</x-ui.button>
            @endunless
        </x-slot:actions>
    </x-ui.page-header>

    @if ($showForm)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-neutral-900">{{ $editingId ? 'Edit' : 'New' }} item</h2>
            <div class="mt-3 grid max-w-md gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Part number (code)</label>
                    <x-ui.input wire:model="code" placeholder="PT6C-67C" />
                    @error('code') <p class="mt-1 text-xs text-danger-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Description</label>
                    <x-ui.input wire:model="description" placeholder="Turboshaft engine" />
                    @error('description') <p class="mt-1 text-xs text-danger-700">{{ $message }}</p> @enderror
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
                <th class="px-3 py-2 font-medium">Part Number</th>
                <th class="px-3 py-2 font-medium">Description</th>
                <th class="px-3 py-2 font-medium text-right">Actions</th>
            </x-slot:head>
            @forelse ($this->items as $item)
                <tr class="hover:bg-neutral-50" wire:key="item-{{ $item->id }}">
                    <td class="px-3 py-2 font-mono text-[13px] text-neutral-700">{{ $item->code }}</td>
                    <td class="px-3 py-2 text-neutral-700">{{ $item->description ?? '—' }}</td>
                    <td class="px-3 py-2 text-right">
                        <button wire:click="edit({{ $item->id }})" class="text-[13px] text-accent-700 hover:underline">Edit</button>
                        <span class="text-neutral-300">·</span>
                        <button wire:click="delete({{ $item->id }})" wire:confirm="Delete this item?" class="text-[13px] text-danger-700 hover:underline">Delete</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-3 py-6 text-center text-sm text-neutral-400">No items.</td></tr>
            @endforelse
        </x-ui.table>
    </div>
</div>
