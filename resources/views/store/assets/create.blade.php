<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">Register asset</h2>
    </x-slot>

    <form method="POST" action="{{ route('store.assets.store') }}" class="max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        <div>
            <x-input-label for="type" value="Type" />
            <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300" required>
                @foreach (App\Enums\StoreAssetType::cases() as $assetType)
                    <option value="{{ $assetType->value }}" @selected(old('type', $defaultType ?: 'tool') === $assetType->value)>{{ $assetType->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="name" value="Name" />
            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required />
        </div>
        <div>
            <x-input-label for="identifier" value="Plate / asset code (optional)" />
            <x-text-input id="identifier" name="identifier" class="mt-1 block w-full" :value="old('identifier')" />
        </div>
        <div>
            <x-input-label for="vehicle_kind" value="Vehicle kind (tractor, lorry…)" />
            <x-text-input id="vehicle_kind" name="vehicle_kind" class="mt-1 block w-full" :value="old('vehicle_kind')" />
        </div>
        <div>
            <x-input-label for="notes" value="Notes" />
            <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('notes') }}</textarea>
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-300 text-amber-500">
            Active
        </label>
        <div class="flex gap-3">
            <x-primary-button>Save</x-primary-button>
            <a href="{{ route('store.assets.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-app-layout>
