@php
    $productOptions = $products->map(fn ($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'sku' => $p->sku,
        'stock' => (float) $p->stock_quantity,
        'unit' => $p->unit?->symbol,
    ])->values();

    $productSelectOptions = $productOptions->map(fn ($p) => [
        'value' => (string) $p['id'],
        'label' => $p['sku'].' — '.$p['name'].' ('.$p['stock'].' '.($p['unit'] ?? '').')',
        'search' => $p['sku'].' '.$p['name'],
    ])->values();

    $projectOptions = $projects->map(fn ($p) => [
        'value' => (string) $p->id,
        'label' => $p->name.' ('.$p->project_code.')',
        'search' => $p->name.' '.$p->project_code,
    ])->values();

    $existingItems = old('items', isset($materialRequest)
        ? $materialRequest->items->map(fn ($item) => [
            'product_id' => (string) $item->product_id,
            'quantity' => (float) $item->quantity_requested,
            'notes' => $item->notes,
        ])->all()
        : [['product_id' => '', 'quantity' => 1, 'notes' => '']]
    );
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">{{ isset($materialRequest) ? 'Edit Request' : 'New Material Request' }}</h2>
    </x-slot>

    <form
        method="POST"
        action="{{ isset($materialRequest) ? route('construction.material-requests.update', $materialRequest) : route('construction.material-requests.store') }}"
        class="space-y-6"
        x-data="requestForm()"
    >
        @csrf
        @isset($materialRequest) @method('PUT') @endisset

        <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:grid-cols-3">
            <div>
                <x-input-label for="project_id" value="Project" />
                <x-searchable-select
                    name="project_id"
                    :options="$projectOptions"
                    :value="(string) old('project_id', $materialRequest->project_id ?? request('project_id'))"
                    placeholder="Search project…"
                    empty-label="Select project"
                    :allow-empty="false"
                    :required="true"
                    class="mt-1"
                />
            </div>
            <div>
                <x-input-label for="request_date" value="Request date" />
                <x-text-input id="request_date" name="request_date" type="date" class="mt-1 block w-full" :value="old('request_date', isset($materialRequest) ? $materialRequest->request_date->format('Y-m-d') : now()->toDateString())" required />
            </div>
            <div>
                <x-input-label for="required_date" value="Required by" />
                <x-text-input id="required_date" name="required_date" type="date" class="mt-1 block w-full" :value="old('required_date', isset($materialRequest) && $materialRequest->required_date ? $materialRequest->required_date->format('Y-m-d') : '')" />
            </div>
            <div class="sm:col-span-3">
                <x-input-label for="notes" value="Notes" />
                <x-text-input id="notes" name="notes" class="mt-1 block w-full" :value="old('notes', $materialRequest->notes ?? '')" />
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b px-5 py-3">
                <h3 class="font-semibold text-slate-800">Materials</h3>
                <button type="button" x-on:click="addItem()" class="btn btn-secondary btn-sm">Add product</button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3 w-28">Qty</th>
                            <th class="px-4 py-3">Notes</th>
                            <th class="px-4 py-3 w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, index) in items" :key="index">
                            <tr class="border-t">
                                <td class="px-4 py-3">
                                    <div class="relative"
                                        x-data="searchableSelect({
                                            options: productSelectOptions,
                                            value: item.product_id,
                                            name: function () { return 'items[' + index + '][product_id]'; },
                                            required: true,
                                            allowEmpty: true,
                                            emptyLabel: 'Select product',
                                            placeholder: 'Search product…',
                                            onChange: function (v) { item.product_id = v; },
                                            getValue: function () { return item.product_id; },
                                        })"
                                        x-on:click.outside="open = false"
                                    >
                                        @include('components.partials.searchable-select-inner')
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" step="0.001" min="0.001" :name="'items['+index+'][quantity]'" x-model.number="item.quantity" class="w-full rounded-md border-gray-300 text-sm" required>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" :name="'items['+index+'][notes]'" x-model="item.notes" class="w-full rounded-md border-gray-300 text-sm">
                                </td>
                                <td class="px-4 py-3">
                                    <button type="button" x-on:click="removeItem(index)" class="btn btn-danger-outline btn-sm">Remove</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <x-primary-button>Save as draft</x-primary-button>
            @unless (isset($materialRequest))
                <button type="submit" name="submit" value="1" class="btn btn-success">
                    Submit for approval
                </button>
            @endunless
            <a href="{{ route('construction.material-requests.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    @push('scripts')
        <script>
            function requestForm() {
                return {
                    products: @json($productOptions),
                    productSelectOptions: @json($productSelectOptions),
                    items: @json($existingItems),
                    addItem() {
                        this.items.push({ product_id: '', quantity: 1, notes: '' });
                    },
                    removeItem(index) {
                        if (this.items.length === 1) return;
                        this.items.splice(index, 1);
                    }
                }
            }
        </script>
    @endpush
</x-app-layout>
