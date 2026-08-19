<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">{{ isset($unit) ? 'Edit Unit' : 'Add Unit' }}</h2>
    </x-slot>

    <form method="POST" action="{{ isset($unit) ? route('store.units.update', $unit) : route('store.units.store') }}" class="max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @isset($unit) @method('PUT') @endisset
        <div>
            <x-input-label for="name" value="Name" />
            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $unit->name ?? '')" required />
        </div>
        <div>
            <x-input-label for="symbol" value="Symbol (e.g. bag, kg, pcs)" />
            <x-text-input id="symbol" name="symbol" class="mt-1 block w-full" :value="old('symbol', $unit->symbol ?? '')" required />
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $unit->is_active ?? true)) class="rounded border-slate-300 text-amber-500">
            Active
        </label>
        <div class="flex gap-3">
            <x-primary-button>Save</x-primary-button>
            <a href="{{ route('store.units.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-app-layout>
