<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">{{ isset($supplier) ? 'Edit Supplier' : 'Add Supplier' }}</h2>
    </x-slot>

    <form method="POST" action="{{ isset($supplier) ? route('store.suppliers.update', $supplier) : route('store.suppliers.store') }}" class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @isset($supplier) @method('PUT') @endisset
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="name" value="Supplier name" />
                <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $supplier->name ?? '')" required />
            </div>
            <div>
                <x-input-label for="contact_person" value="Contact person" />
                <x-text-input id="contact_person" name="contact_person" class="mt-1 block w-full" :value="old('contact_person', $supplier->contact_person ?? '')" />
            </div>
            <div>
                <x-input-label for="phone" value="Phone" />
                <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone', $supplier->phone ?? '')" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="email" value="Email" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $supplier->email ?? '')" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="address" value="Address" />
                <textarea id="address" name="address" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old('address', $supplier->address ?? '') }}</textarea>
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="notes" value="Notes" />
                <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old('notes', $supplier->notes ?? '') }}</textarea>
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->is_active ?? true)) class="rounded border-slate-300 text-amber-500">
            Active
        </label>
        <div class="flex gap-3">
            <x-primary-button>Save</x-primary-button>
            <a href="{{ route('store.suppliers.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-app-layout>
