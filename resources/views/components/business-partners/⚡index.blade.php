<?php

use App\Models\BusinessPartner;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $code = '';
    public string $name = '';
    public string $partnerType = 'Customer';
    public string $contactName = '';
    public string $email = '';

    #[Computed]
    public function partners()
    {
        return BusinessPartner::orderBy('code')->get();
    }

    public function new(): void
    {
        $this->reset(['editingId', 'code', 'name', 'partnerType', 'contactName', 'email']);
        $this->partnerType = 'Customer';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $p = BusinessPartner::findOrFail($id);
        $this->editingId = $p->id;
        $this->code = $p->code;
        $this->name = $p->name;
        $this->partnerType = $p->partner_type;
        $this->contactName = (string) $p->contact_name;
        $this->email = (string) $p->email;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'code', 'name', 'partnerType', 'contactName', 'email', 'showForm']);
    }

    public function save(): void
    {
        $data = $this->validate([
            'code' => ['required', 'string', 'max:50', 'unique:business_partners,code'.($this->editingId ? ','.$this->editingId : '')],
            'name' => ['required', 'string', 'max:255'],
            'partnerType' => ['required', 'in:Customer,Operator,Owner,Vendor'],
            'contactName' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $attrs = [
            'code' => $data['code'], 'name' => $data['name'], 'partner_type' => $data['partnerType'],
            'contact_name' => $data['contactName'] ?: null, 'email' => $data['email'] ?: null,
        ];
        if ($this->editingId) {
            BusinessPartner::findOrFail($this->editingId)->update($attrs);
        } else {
            BusinessPartner::create($attrs);
        }

        $this->reset(['editingId', 'code', 'name', 'partnerType', 'contactName', 'email', 'showForm']);
        unset($this->partners);
    }

    public function delete(int $id): void
    {
        BusinessPartner::findOrFail($id)->delete();
        unset($this->partners);
    }
};
?>

@php $typeTone = ['Customer' => 'info', 'Operator' => 'ok', 'Owner' => 'due-soon', 'Vendor' => 'neutral']; @endphp

<div>
    <x-ui.page-header title="Business Partners" subtitle="Customers · Operators · Owners · Vendors">
        <x-slot:actions>
            @unless ($showForm)
                <x-ui.button variant="primary" size="sm" wire:click="new">New Partner</x-ui.button>
            @endunless
        </x-slot:actions>
    </x-ui.page-header>

    @if ($showForm)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-neutral-900">{{ $editingId ? 'Edit' : 'New' }} partner</h2>
            <div class="mt-3 grid max-w-md gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Code</label>
                    <x-ui.input wire:model="code" placeholder="BP-001" />
                    @error('code') <p class="mt-1 text-xs text-danger-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Name</label>
                    <x-ui.input wire:model="name" />
                    @error('name') <p class="mt-1 text-xs text-danger-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Type</label>
                    <x-ui.select wire:model="partnerType">
                        <option>Customer</option><option>Operator</option><option>Owner</option><option>Vendor</option>
                    </x-ui.select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Contact</label>
                    <x-ui.input wire:model="contactName" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Email</label>
                    <x-ui.input type="email" wire:model="email" />
                    @error('email') <p class="mt-1 text-xs text-danger-700">{{ $message }}</p> @enderror
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
                <th class="px-3 py-2 font-medium">Type</th>
                <th class="px-3 py-2 font-medium">Contact</th>
                <th class="px-3 py-2 font-medium text-right">Actions</th>
            </x-slot:head>
            @forelse ($this->partners as $p)
                <tr class="hover:bg-neutral-50" wire:key="bp-{{ $p->id }}">
                    <td class="px-3 py-2 font-mono text-[13px] text-neutral-700">{{ $p->code }}</td>
                    <td class="px-3 py-2 text-neutral-700">{{ $p->name }}</td>
                    <td class="px-3 py-2"><x-ui.status-pill :tone="$typeTone[$p->partner_type] ?? 'neutral'">{{ $p->partner_type }}</x-ui.status-pill></td>
                    <td class="px-3 py-2 text-neutral-500">{{ $p->contact_name ?? '—' }}</td>
                    <td class="px-3 py-2 text-right">
                        <button wire:click="edit({{ $p->id }})" class="text-[13px] text-accent-700 hover:underline">Edit</button>
                        <span class="text-neutral-300">·</span>
                        <button wire:click="delete({{ $p->id }})" wire:confirm="Delete this partner?" class="text-[13px] text-danger-700 hover:underline">Delete</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-3 py-6 text-center text-sm text-neutral-400">No business partners.</td></tr>
            @endforelse
        </x-ui.table>
    </div>
</div>
