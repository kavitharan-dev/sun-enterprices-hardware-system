<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">{{ isset($project) ? 'Edit Project' : 'New Project' }}</h2>
    </x-slot>

    <form method="POST" action="{{ isset($project) ? route('construction.projects.update', $project) : route('construction.projects.store') }}" class="max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @isset($project) @method('PUT') @endisset
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="name" value="Project name" />
                <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $project->name ?? '')" required />
            </div>
            <div>
                <x-input-label for="customer_id" value="Customer" />
                <select id="customer_id" name="customer_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                    <option value="">Select customer</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id', $project->customer_id ?? '') == $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="site_manager_id" value="Site manager" />
                <select id="site_manager_id" name="site_manager_id" class="mt-1 block w-full rounded-md border-gray-300">
                    <option value="">Unassigned</option>
                    @foreach ($siteManagers as $manager)
                        <option value="{{ $manager->id }}" @selected(old('site_manager_id', $project->site_manager_id ?? '') == $manager->id)>{{ $manager->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="location" value="Location" />
                <x-text-input id="location" name="location" class="mt-1 block w-full" :value="old('location', $project->location ?? '')" required />
            </div>
            <div>
                <x-input-label for="budget" value="Budget (Rs.)" />
                <x-text-input id="budget" name="budget" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('budget', $project->budget ?? 0)" required />
            </div>
            <div>
                <x-input-label for="status" value="Status" />
                <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300" required>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('status', isset($project) ? $project->status->value : 'planning') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="start_date" value="Start date" />
                <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date', isset($project) ? $project->start_date->format('Y-m-d') : now()->toDateString())" required />
            </div>
            <div>
                <x-input-label for="expected_end_date" value="Expected end date" />
                <x-text-input id="expected_end_date" name="expected_end_date" type="date" class="mt-1 block w-full" :value="old('expected_end_date', isset($project) && $project->expected_end_date ? $project->expected_end_date->format('Y-m-d') : '')" />
            </div>
            <div class="sm:col-span-2">
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('description', $project->description ?? '') }}</textarea>
            </div>
        </div>
        <div class="flex gap-3">
            <x-primary-button>Save</x-primary-button>
            <a href="{{ route('construction.projects.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-app-layout>
