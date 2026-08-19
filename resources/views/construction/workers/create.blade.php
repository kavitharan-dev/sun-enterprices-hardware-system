<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">{{ isset($worker) ? 'Edit Worker' : 'Add Worker' }}</h2>
    </x-slot>

    <form method="POST" action="{{ isset($worker) ? route('construction.workers.update', $worker) : route('construction.workers.store') }}" class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @isset($worker) @method('PUT') @endisset
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="name" value="Worker name" />
                <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $worker->name ?? '')" required />
            </div>
            <div>
                <x-input-label for="job_role" value="Job role" />
                <x-text-input id="job_role" name="job_role" class="mt-1 block w-full" :value="old('job_role', $worker->job_role ?? '')" placeholder="Mason, labourer, carpenter..." />
            </div>
            <div>
                <x-input-label for="daily_rate" value="Daily rate (Rs.)" />
                <x-text-input id="daily_rate" name="daily_rate" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('daily_rate', $worker->daily_rate ?? 0)" />
            </div>
            <div>
                <x-input-label for="weekly_salary" value="Weekly salary (Rs.)" />
                <x-text-input id="weekly_salary" name="weekly_salary" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('weekly_salary', $worker->weekly_salary ?? 0)" />
                <p class="mt-1 text-xs text-slate-500">Paid every Saturday. Used for advances and debt.</p>
            </div>
            <div>
                <x-input-label for="phone" value="Phone" />
                <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone', $worker->phone ?? '')" />
            </div>
            <div>
                <x-input-label for="nic" value="NIC" />
                <x-text-input id="nic" name="nic" class="mt-1 block w-full" :value="old('nic', $worker->nic ?? '')" />
            </div>
            <div>
                <x-input-label for="join_date" value="Join date" />
                <x-text-input id="join_date" name="join_date" type="date" class="mt-1 block w-full" :value="old('join_date', isset($worker) && $worker->join_date ? $worker->join_date->format('Y-m-d') : '')" />
            </div>
            <div>
                <x-input-label for="status" value="Status" />
                <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300" required>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('status', isset($worker) ? $worker->status->value : 'active') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex gap-3">
            <x-primary-button>Save</x-primary-button>
            <a href="{{ route('construction.workers.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-app-layout>
