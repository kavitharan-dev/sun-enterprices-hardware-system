<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">{{ isset($brand) ? 'Edit Brand' : 'Add Brand' }}</h2>
    </x-slot>

    <form method="POST" action="{{ isset($brand) ? route('store.brands.update', $brand) : route('store.brands.store') }}" class="max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @isset($brand) @method('PUT') @endisset
        <div>
            <x-input-label for="name" value="Name" />
            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $brand->name ?? '')" required />
        </div>
        <div>
            <x-input-label for="description" value="Description" />
            <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('description', $brand->description ?? '') }}</textarea>
        </div>
        <div>
            <x-input-label for="sort_order" value="Sort order" />
            <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="old('sort_order', $brand->sort_order ?? 0)" />
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand->is_active ?? true)) class="rounded border-slate-300 text-amber-500">
            Active
        </label>
        <div class="flex gap-3">
            <x-primary-button>Save</x-primary-button>
            <a href="{{ route('store.brands.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-app-layout>
