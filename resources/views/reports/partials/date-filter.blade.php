<form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-xs font-medium text-slate-500">From</label>
        <input type="date" name="from" value="{{ $from }}" class="rounded-lg border-slate-300 text-sm">
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-500">To</label>
        <input type="date" name="to" value="{{ $to }}" class="rounded-lg border-slate-300 text-sm">
    </div>
    <button class="btn btn-dark">Apply</button>
    <a href="{{ request()->fullUrlWithQuery(['export' => 1]) }}" class="btn btn-secondary">Export CSV</a>
</form>
