<x-layouts.app title="ATP 3.0 v2 — Design System">
    <div class="mx-auto max-w-4xl px-6 py-10 space-y-8">
        <header>
            <h1 class="text-base font-semibold tracking-tight text-neutral-900">ATP 3.0 v2 — Design System</h1>
            <p class="mt-1 text-sm text-neutral-500">Phase 6 foundation. Linear-like: muted neutrals, one indigo accent, crisp borders, tabular data.</p>
        </header>

        <x-ui.card title="Accent & neutrals">
            <div class="flex flex-wrap gap-1.5">
                @foreach (['50','100','200','300','400','500','600','700','800','900','950'] as $s)
                    <div class="h-10 w-10 rounded-md border border-neutral-200" style="background: var(--color-neutral-{{ $s }})" title="neutral-{{ $s }}"></div>
                @endforeach
            </div>
            <div class="mt-3 flex gap-1.5">
                @foreach (['50','100','300','500','600','700'] as $s)
                    <div class="h-10 w-10 rounded-md border border-neutral-200" style="background: var(--color-accent-{{ $s }})" title="accent-{{ $s }}"></div>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card title="Buttons">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button variant="primary">Primary</x-ui.button>
                <x-ui.button variant="secondary">Secondary</x-ui.button>
                <x-ui.button variant="ghost">Ghost</x-ui.button>
                <x-ui.button variant="danger">Danger</x-ui.button>
                <x-ui.button variant="primary" size="sm">Small</x-ui.button>
                <x-ui.button variant="secondary" disabled>Disabled</x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card title="Status pills (MRO / airworthiness vocabulary)">
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.status-pill tone="ok">OK</x-ui.status-pill>
                <x-ui.status-pill tone="due-soon">Due Soon</x-ui.status-pill>
                <x-ui.status-pill tone="overdue">Overdue</x-ui.status-pill>
                <x-ui.status-pill tone="info">Planned</x-ui.status-pill>
                <x-ui.status-pill tone="neutral">Not Evaluated</x-ui.status-pill>
            </div>
        </x-ui.card>

        <x-ui.card title="Inputs">
            <div class="grid max-w-sm gap-2">
                <x-ui.input placeholder="Registration (e.g. 9M-WBD)" />
                <x-ui.input type="number" value="1240.50" data-numeric />
                <x-ui.input placeholder="Disabled" disabled />
            </div>
        </x-ui.card>

        <x-ui.card title="Dense table (tabular numerals)" :padded="false">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 text-left text-xs text-neutral-500">
                        <th class="px-4 py-2 font-medium">Counter</th>
                        <th class="px-4 py-2 font-medium text-right">Value</th>
                        <th class="px-4 py-2 font-medium text-right">Remaining</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ([['TSN','12404.5','—','ok'],['TSO','842.0','158.0','due-soon'],['CSN','9821','—','ok'],['LLP-1','4980.0','-20.0','overdue']] as $r)
                        <tr class="hover:bg-neutral-50">
                            <td class="px-4 py-2 font-mono text-[13px] text-neutral-700">{{ $r[0] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $r[1] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-neutral-500">{{ $r[2] }}</td>
                            <td class="px-4 py-2"><x-ui.status-pill tone="{{ $r[3] }}">{{ ucfirst(str_replace('-',' ',$r[3])) }}</x-ui.status-pill></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
        <x-ui.card title="Form controls">
            <div class="grid max-w-sm gap-3">
                <x-ui.select>
                    <option>Overdue</option>
                    <option>Due Soon</option>
                    <option>OK</option>
                </x-ui.select>
                <x-ui.textarea rows="2" placeholder="Remarks…"></x-ui.textarea>
                <x-ui.checkbox label="Propagate to installed components" />
            </div>
        </x-ui.card>

        <x-ui.card title="Page header + table shell" :padded="false">
            <div class="p-4">
                <x-ui.page-header title="Fleet Management" subtitle="9 aircraft">
                    <x-slot:actions>
                        <x-ui.button variant="secondary" size="sm">Export</x-ui.button>
                        <x-ui.button variant="primary" size="sm">New Aircraft</x-ui.button>
                    </x-slot:actions>
                </x-ui.page-header>
                <div class="mt-3">
                    <x-ui.table>
                        <x-slot:head>
                            <th class="px-3 py-2 font-medium">Registration</th>
                            <th class="px-3 py-2 font-medium">Type</th>
                            <th class="px-3 py-2 font-medium">Airworthiness</th>
                        </x-slot:head>
                        <tr class="hover:bg-neutral-50">
                            <td class="px-3 py-2 font-medium text-accent-700">9M-WBD</td>
                            <td class="px-3 py-2 text-neutral-600">AW139</td>
                            <td class="px-3 py-2"><x-ui.status-pill tone="ok">Airworthy</x-ui.status-pill></td>
                        </tr>
                        <tr class="hover:bg-neutral-50">
                            <td class="px-3 py-2 font-medium text-accent-700">M104-04</td>
                            <td class="px-3 py-2 text-neutral-600">AW139</td>
                            <td class="px-3 py-2"><x-ui.status-pill tone="overdue">Not Airworthy</x-ui.status-pill></td>
                        </tr>
                    </x-ui.table>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card title="Modal">
            <x-ui.modal title="Record Counter">
                <x-slot:trigger>
                    <x-ui.button variant="secondary">Open modal</x-ui.button>
                </x-slot:trigger>
                <p class="text-sm text-neutral-600">Modal body — Alpine-driven, Escape and backdrop close, focus-trapped.</p>
            </x-ui.modal>
        </x-ui.card>
    </div>
</x-layouts.app>
