<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\BrandRequest;
use App\Models\Brand;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BrandController extends Controller
{
    use LogsActivity;

    public function index(): View
    {
        $this->authorize('viewAny', Brand::class);

        $brands = Brand::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return view('store.brands.index', compact('brands'));
    }

    public function create(): View
    {
        $this->authorize('create', Brand::class);

        return view('store.brands.create');
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        $brand = Brand::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->logActivity('created', 'Brand', "Created brand {$brand->name}", $brand);

        return redirect()->route('store.brands.index')->with('success', 'Brand created.');
    }

    public function edit(Brand $brand): View
    {
        $this->authorize('update', $brand);

        return view('store.brands.edit', compact('brand'));
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $this->authorize('update', $brand);

        $brand->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->logActivity('updated', 'Brand', "Updated brand {$brand->name}", $brand);

        return redirect()->route('store.brands.index')->with('success', 'Brand updated.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $this->authorize('delete', $brand);

        if ($brand->products()->exists()) {
            return back()->with('error', 'This brand has products and cannot be deleted.');
        }

        $name = $brand->name;
        $brand->delete();

        $this->logActivity('deleted', 'Brand', "Deleted brand {$name}");

        return redirect()->route('store.brands.index')->with('success', 'Brand deleted.');
    }
}
