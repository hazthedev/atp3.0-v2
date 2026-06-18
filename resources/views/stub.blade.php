<x-layouts.shell :title="ucwords(str_replace('-', ' ', $module))" :active="$module">
    <x-ui.page-header :title="ucwords(str_replace('-', ' ', $module))" subtitle="Not built yet" />
    <div class="mt-6 rounded-lg border border-dashed border-neutral-300 bg-white p-10 text-center">
        <p class="text-sm text-neutral-500">This module's screens are not built yet.</p>
        <p class="mt-1 text-xs text-neutral-400">Domain logic for the platform is complete and tested; UI is being built module by module.</p>
    </div>
</x-layouts.shell>
