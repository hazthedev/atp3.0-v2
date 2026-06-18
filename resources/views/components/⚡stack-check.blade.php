<?php

use Livewire\Component;

new class extends Component
{
    public string $php;
    public string $laravel;
    public string $livewire;

    public function mount(): void
    {
        $this->php = PHP_VERSION;
        $this->laravel = app()->version();
        $this->livewire = \Composer\InstalledVersions::getPrettyVersion('livewire/livewire') ?? 'unknown';
    }
};
?>

<div class="min-h-full grid place-items-center p-8">
    <div class="w-full max-w-sm rounded-lg border border-neutral-200 p-6">
        <h1 class="text-sm font-semibold tracking-tight">ATP 3.0 v2 — stack check</h1>
        <p class="mt-1 text-xs text-neutral-500">Phase 1 scaffold. Real design system lands in Phase 6.</p>

        <dl class="mt-4 space-y-1.5 text-sm">
            <div class="flex justify-between"><dt class="text-neutral-500">PHP</dt><dd class="font-medium tabular-nums">{{ $php }}</dd></div>
            <div class="flex justify-between"><dt class="text-neutral-500">Laravel</dt><dd class="font-medium tabular-nums">{{ $laravel }}</dd></div>
            <div class="flex justify-between"><dt class="text-neutral-500">Livewire</dt><dd class="font-medium tabular-nums">{{ $livewire }}</dd></div>
        </dl>

        <div class="mt-5 border-t border-neutral-100 pt-4" x-data="{ n: 0 }">
            <button type="button" @click="n++"
                class="rounded-md border border-neutral-200 px-2.5 py-1 text-sm hover:bg-neutral-50">
                Alpine OK · <span x-text="n" class="tabular-nums">0</span>
            </button>
        </div>
    </div>
</div>
