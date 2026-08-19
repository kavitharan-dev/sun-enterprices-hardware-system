<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">Reports</h2>
    </x-slot>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @if (auth()->user()->canViewStoreReports())
            <a href="{{ route('reports.sales') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-amber-300">
                <p class="font-semibold text-slate-900">Sales</p>
                <p class="mt-1 text-sm text-slate-500">Completed invoices, totals, and product mix.</p>
            </a>
            <a href="{{ route('reports.purchases') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-amber-300">
                <p class="font-semibold text-slate-900">Purchases</p>
                <p class="mt-1 text-sm text-slate-500">Goods received from suppliers.</p>
            </a>
            <a href="{{ route('reports.inventory') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-amber-300">
                <p class="font-semibold text-slate-900">Inventory</p>
                <p class="mt-1 text-sm text-slate-500">Current stock quantities and value.</p>
            </a>
            <a href="{{ route('reports.movements') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-amber-300">
                <p class="font-semibold text-slate-900">Stock movements</p>
                <p class="mt-1 text-sm text-slate-500">Ledger of every stock in and out.</p>
            </a>
            <a href="{{ route('reports.outstanding') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-amber-300">
                <p class="font-semibold text-slate-900">Outstanding payments</p>
                <p class="mt-1 text-sm text-slate-500">Completed sales still carrying a balance.</p>
            </a>
        @endif
        @if (auth()->user()->canViewConstructionReports())
            <a href="{{ route('reports.projects') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-amber-300">
                <p class="font-semibold text-slate-900">Projects</p>
                <p class="mt-1 text-sm text-slate-500">Budget vs spent and progress.</p>
            </a>
            <a href="{{ route('reports.expenses') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-amber-300">
                <p class="font-semibold text-slate-900">Project expenses</p>
                <p class="mt-1 text-sm text-slate-500">Labour, materials, and other site costs.</p>
            </a>
            <a href="{{ route('reports.issues') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-amber-300">
                <p class="font-semibold text-slate-900">Material issues</p>
                <p class="mt-1 text-sm text-slate-500">Stock issued to construction projects.</p>
            </a>
        @endif
    </div>
</x-app-layout>
