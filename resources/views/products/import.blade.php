<x-app-layout title="Import Products">
    <x-page-header title="Import Products" subtitle="Add many products at once from Excel or CSV." :breadcrumbs="['Inventory' => null, 'Products' => route('products.index'), 'Import' => null]">
        <x-button :href="route('products.index')" variant="outline" icon="chevron-left">Back to products</x-button>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="1. Download the template" subtitle="Fill one product per row. Keep the header row as it is.">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-success/10 text-emerald-600"><x-icon name="file-text" size="w-6 h-6" /></span>
                        <div>
                            <p class="font-semibold text-heading">products-import-template.xlsx</p>
                            <p class="text-xs text-muted">Includes an example row and one opening-stock column per branch.</p>
                        </div>
                    </div>
                    <x-button :href="route('products.import.template')" variant="success" icon="download">Download template</x-button>
                </div>
            </x-card>

            <x-card title="2. Upload your file" subtitle="XLSX, XLS or CSV, up to 5 MB.">
                <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" x-data="{ name: null, over: false }" class="space-y-4">
                    @csrf
                    <label class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-10 text-center transition"
                        :class="over ? 'border-primary bg-primary/5' : 'border-line hover:border-primary/40 hover:bg-subtle'"
                        @dragover.prevent="over = true" @dragleave="over = false"
                        @drop.prevent="over = false; $refs.file.files = $event.dataTransfer.files; name = $event.dataTransfer.files[0]?.name">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary"><x-icon name="upload" size="w-7 h-7" /></span>
                        <span class="mt-3 font-semibold text-heading" x-text="name ?? 'Drop your file here or click to browse'"></span>
                        <span class="mt-1 text-xs text-muted">Rows with errors are skipped and listed in a report.</span>
                        <input type="file" name="file" x-ref="file" class="sr-only" accept=".xlsx,.xls,.csv" required @change="name = $event.target.files[0]?.name">
                    </label>
                    @error('file')<p class="form-error">{{ $message }}</p>@enderror
                    <div class="flex justify-end">
                        <x-button type="submit" icon="upload" loading>Import products</x-button>
                    </div>
                </form>
            </x-card>

            @if ($report)
                <x-card title="Import report" :subtitle="$report['imported'].' imported · '.count($report['failures']).' failed'">
                    <x-slot name="actions">
                        <x-badge color="success">{{ $report['imported'] }} imported</x-badge>
                        @if ($report['failures'])<x-badge color="danger">{{ count($report['failures']) }} failed</x-badge>@endif
                    </x-slot>
                    @if ($report['failures'])
                        <div class="-mx-6 -mb-6 overflow-x-auto">
                            <table class="data-table">
                                <thead><tr><th>Row</th><th>Product</th><th>Problems</th></tr></thead>
                                <tbody>
                                    @foreach ($report['failures'] as $failure)
                                        <tr>
                                            <td class="tabular font-semibold text-heading">#{{ $failure['row'] }}</td>
                                            <td>{{ $failure['name'] }}</td>
                                            <td>
                                                <ul class="space-y-1">
                                                    @foreach ($failure['errors'] as $error)
                                                        <li class="flex items-start gap-1.5 text-xs text-red-600 dark:text-red-400"><x-icon name="x-circle" size="w-3.5 h-3.5 mt-px shrink-0" /> {{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <x-empty-state icon="check-circle" title="Every row imported" description="Your products are ready in the catalogue.">
                            <x-button :href="route('products.index', ['sort' => 'latest'])" icon="package">View products</x-button>
                        </x-empty-state>
                    @endif
                </x-card>
            @endif
        </div>

        <x-card title="Column guide">
            <ul class="space-y-3 text-sm">
                @foreach ([
                    'name' => 'Required. Product name.',
                    'sku' => 'Optional. Auto-generated if empty.',
                    'barcode' => 'Optional. EAN-13 generated if empty.',
                    'category / brand' => 'Optional. Created if the name is new.',
                    'unit' => 'Required. Short name: pcs, kg, ltr, dozen, box, pack.',
                    'purchase_price, sale_price' => 'Required numbers.',
                    'wholesale_price, tax_percent, min_stock' => 'Optional numbers.',
                    'track_expiry' => 'yes / no',
                    'stock_hyd, stock_khi…' => auth()->user()->isOwner() ? 'Opening stock per branch.' : 'Opening stock (only your branch column is used).',
                ] as $column => $help)
                    <li>
                        <code class="rounded bg-subtle px-1.5 py-0.5 text-xs font-semibold text-heading">{{ $column }}</code>
                        <p class="mt-1 text-xs text-muted">{{ $help }}</p>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>
</x-app-layout>
