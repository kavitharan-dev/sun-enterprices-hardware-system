<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\UnitRequest;
use App\Models\Unit;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UnitController extends Controller
{
    use LogsActivity;

    public function index(): View
    {
        $this->authorize('viewAny', Unit::class);

        $units = Unit::query()
            ->withCount('products')
            ->orderBy('name')
            ->paginate(20);

        return view('store.units.index', compact('units'));
    }

    public function create(): View
    {
        $this->authorize('create', Unit::class);

        return view('store.units.create');
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        $unit = Unit::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->logActivity('created', 'Unit', "Created unit {$unit->name}", $unit);

        return redirect()->route('store.units.index')->with('success', 'Unit created.');
    }

    public function edit(Unit $unit): View
    {
        $this->authorize('update', $unit);

        return view('store.units.edit', compact('unit'));
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $this->authorize('update', $unit);

        $unit->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->logActivity('updated', 'Unit', "Updated unit {$unit->name}", $unit);

        return redirect()->route('store.units.index')->with('success', 'Unit updated.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $this->authorize('delete', $unit);

        if ($unit->products()->exists()) {
            return back()->with('error', 'This unit is used by products and cannot be deleted.');
        }

        $name = $unit->name;
        $unit->delete();

        $this->logActivity('deleted', 'Unit', "Deleted unit {$name}");

        return redirect()->route('store.units.index')->with('success', 'Unit deleted.');
    }
}
