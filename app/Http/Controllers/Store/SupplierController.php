<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\SupplierRequest;
use App\Models\Supplier;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    use LogsActivity;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = Supplier::query()
            ->withCount('purchases')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('contact_person', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('store.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('store.suppliers.create');
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->logActivity('created', 'Supplier', "Created supplier {$supplier->name}", $supplier);

        return redirect()->route('store.suppliers.index')->with('success', 'Supplier created.');
    }

    public function show(Supplier $supplier): View
    {
        $this->authorize('view', $supplier);

        $supplier->load(['purchases' => fn ($query) => $query->latest()->limit(10)]);

        return view('store.suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('store.suppliers.edit', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $supplier->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->logActivity('updated', 'Supplier', "Updated supplier {$supplier->name}", $supplier);

        return redirect()->route('store.suppliers.index')->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->purchases()->exists()) {
            return back()->with('error', 'This supplier has purchases and cannot be deleted. Deactivate instead.');
        }

        $name = $supplier->name;
        $supplier->delete();

        $this->logActivity('deleted', 'Supplier', "Deleted supplier {$name}");

        return redirect()->route('store.suppliers.index')->with('success', 'Supplier deleted.');
    }
}
