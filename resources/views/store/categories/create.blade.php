<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">{{ isset($category) ? 'Edit Category' : 'Add Category' }}</h2>
    </x-slot>

    <form method="POST" action="{{ isset($category) ? route('store.categories.update', $category) : route('store.categories.store') }}" class="max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @isset($category) @method('PUT') @endisset
        <div>
            <x-input-label for="name" value="Name" />
            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $category->name ?? '')" required />
        </div>
        <div>
            <x-input-label for="description" value="Description" />
            <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('description', $category->description ?? '') }}</textarea>
        </div>
        <div>
            <x-input-label for="sort_order" value="Sort order" />
            <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="old('sort_order', $category->sort_order ?? 0)" />
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true)) class="rounded border-slate-300 text-amber-500">
            Active
        </label>
        <div class="flex gap-3">
            <x-primary-button>Save</x-primary-button>
            <a href="{{ route('store.categories.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-app-layout>
