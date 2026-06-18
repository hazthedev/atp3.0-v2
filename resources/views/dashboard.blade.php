<x-layouts.shell title="Dashboard" active="dashboard">
    <x-ui.page-header title="Dashboard" subtitle="ATP 3.0 v2" />
    <div class="mt-4 grid gap-3 sm:grid-cols-3">
        <x-ui.card title="Fleet">
            <a href="{{ route('fleet.index') }}" class="text-sm text-accent-700 hover:underline">View aircraft →</a>
        </x-ui.card>
        <x-ui.card title="Design system">
            <a href="{{ route('design') }}" class="text-sm text-accent-700 hover:underline">Components →</a>
        </x-ui.card>
        <x-ui.card title="Status">
            <p class="text-sm text-neutral-500">Phase 6 in progress — Fleet counters live.</p>
        </x-ui.card>
    </div>
</x-layouts.shell>
