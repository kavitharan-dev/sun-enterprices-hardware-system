<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Categories</h2>
            <a href="{{ route('store.categories.create') }}" class="btn btn-primary">Add Category</a>
        </div>
    </x-slot>

    <div class="space-y-4">
        @include('store.partials.catalog-nav')

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Products</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($categories as $category)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $category->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $category->products_count }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$category->is_active ? 'active' : 'inactive'" /></td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <a href="{{ route('store.categories.edit', $category) }}" class="btn btn-secondary btn-sm">Edit</a>
                                <form method="POST" action="{{ route('store.categories.destroy', $category) }}" class="inline" onsubmit="return confirm('Delete this category?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger-outline btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($categories->hasPages())
                <div class="border-t px-4 py-3">{{ $categories->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
