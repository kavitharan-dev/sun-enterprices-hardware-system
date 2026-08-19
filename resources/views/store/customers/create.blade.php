<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">{{ isset($customer) ? 'Edit Customer' : 'Add Customer' }}</h2>
    </x-slot>

    <form method="POST" action="{{ isset($customer) ? route('store.customers.update', $customer) : route('store.customers.store') }}" class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @isset($customer) @method('PUT') @endisset
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="name" value="Customer name" />
                <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $customer->name ?? '')" required />
            </div>
            <div>
                <x-input-label for="phone" value="Phone" />
                <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone', $customer->phone ?? '')" />
            </div>
            <div>
                <x-input-label for="nic" value="NIC" />
                <x-text-input id="nic" name="nic" class="mt-1 block w-full" :value="old('nic', $customer->nic ?? '')" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="email" value="Email" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $customer->email ?? '')" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="address" value="Address" />
                <textarea id="address" name="address" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old('address', $customer->address ?? '') }}</textarea>
            </div>
            <div>
                <x-input-label for="credit_limit" value="Credit limit (Rs.)" />
                <x-text-input id="credit_limit" name="credit_limit" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('credit_limit', $customer->credit_limit ?? 0)" />
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active ?? true)) class="rounded border-slate-300 text-amber-500">
            Active
        </label>
        <div class="flex gap-3">
            <x-primary-button>Save</x-primary-button>
            <a href="{{ route('store.customers.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-app-layout>
