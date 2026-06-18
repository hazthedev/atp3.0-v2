<?php

use App\Models\Employee;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $employeeNo = '';
    public string $name = '';
    public string $email = '';

    #[Computed]
    public function employees()
    {
        return Employee::orderBy('employee_no')->get();
    }

    public function new(): void
    {
        $this->reset(['editingId', 'employeeNo', 'name', 'email']);
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $e = Employee::findOrFail($id);
        $this->editingId = $e->id;
        $this->employeeNo = $e->employee_no;
        $this->name = $e->name;
        $this->email = (string) $e->email;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'employeeNo', 'name', 'email', 'showForm']);
    }

    public function save(): void
    {
        $data = $this->validate([
            'employeeNo' => ['required', 'string', 'max:50', 'unique:employees,employee_no'.($this->editingId ? ','.$this->editingId : '')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $attrs = ['employee_no' => $data['employeeNo'], 'name' => $data['name'], 'email' => $data['email'] ?: null];
        if ($this->editingId) {
            Employee::findOrFail($this->editingId)->update($attrs);
        } else {
            Employee::create($attrs);
        }

        $this->reset(['editingId', 'employeeNo', 'name', 'email', 'showForm']);
        unset($this->employees);
    }

    public function delete(int $id): void
    {
        Employee::findOrFail($id)->delete();
        unset($this->employees);
    }
};
?>

<div>
    <x-ui.page-header title="Employees" subtitle="Human Resources">
        <x-slot:actions>
            @unless ($showForm)
                <x-ui.button variant="primary" size="sm" wire:click="new">New Employee</x-ui.button>
            @endunless
        </x-slot:actions>
    </x-ui.page-header>

    @if ($showForm)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-neutral-900">{{ $editingId ? 'Edit' : 'New' }} employee</h2>
            <div class="mt-3 grid max-w-md gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Employee No.</label>
                    <x-ui.input wire:model="employeeNo" placeholder="EMP-001" />
                    @error('employeeNo') <p class="mt-1 text-xs text-danger-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">Name</label>
                    <x-ui.input wire:model="name" />
                    @error('name') <p class="mt-1 text-xs text-danger-700">{{ $message }}</p> @enderror
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
                <th class="px-3 py-2 font-medium">Employee No.</th>
                <th class="px-3 py-2 font-medium">Name</th>
                <th class="px-3 py-2 font-medium">Email</th>
                <th class="px-3 py-2 font-medium text-right">Actions</th>
            </x-slot:head>
            @forelse ($this->employees as $e)
                <tr class="hover:bg-neutral-50" wire:key="emp-{{ $e->id }}">
                    <td class="px-3 py-2 font-mono text-[13px] text-neutral-700">{{ $e->employee_no }}</td>
                    <td class="px-3 py-2 text-neutral-700">{{ $e->name }}</td>
                    <td class="px-3 py-2 text-neutral-500">{{ $e->email ?? '—' }}</td>
                    <td class="px-3 py-2 text-right">
                        <button wire:click="edit({{ $e->id }})" class="text-[13px] text-accent-700 hover:underline">Edit</button>
                        <span class="text-neutral-300">·</span>
                        <button wire:click="delete({{ $e->id }})" wire:confirm="Delete this employee?" class="text-[13px] text-danger-700 hover:underline">Delete</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-6 text-center text-sm text-neutral-400">No employees.</td></tr>
            @endforelse
        </x-ui.table>
    </div>
</div>
