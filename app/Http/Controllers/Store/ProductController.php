<?php

namespace App\Http\Controllers\Store;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\StockService;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    use LogsActivity;

    public function __construct(private readonly StockService $stockService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->with(['category', 'brand', 'unit'])
            ->when($request->filled('q'), fn ($query) => $query->search($request->string('q')->toString()))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->boolean('low_stock'), fn ($query) => $query->lowStock())
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $categories = Category::query()->orderBy('name')->get();

        return view('store.products.index', compact('products', 'categories'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('store.products.create', $this->formData());
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('opening_stock');
        $data['sku'] = $data['sku'] ?: $this->nextSku();
        $data['is_active'] = $request->boolean('is_active', true);
        $data['stock_quantity'] = 0;

        $product = Product::query()->create($data);

        $opening = (float) $request->input('opening_stock', 0);

        if ($opening > 0) {
            $this->stockService->record(
                product: $product,
                type: MovementType::OpeningBalance,
                quantity: $opening,
                unitCost: (float) $product->purchase_price,
                reference: $product,
                notes: 'Opening stock on product create',
            );
        }

        $this->logActivity('created', 'Product', "Created product {$product->name} ({$product->sku})", $product);

        return redirect()->route('store.products.index')->with('success', 'Product created.');
    }

    public function show(Product $product): View
    {
        $this->authorize('view', $product);

        $product->load(['category', 'brand', 'unit']);

        $movements = $product->stockMovements()
            ->with('user')
            ->latest()
            ->paginate(20);

        return view('store.products.show', compact('product', 'movements'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('store.products.edit', [...$this->formData(), 'product' => $product]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $data = $request->safe()->except('opening_stock');
        $data['sku'] = $data['sku'] ?: $product->sku;
        $data['is_active'] = $request->boolean('is_active');

        $original = $product->only(['purchase_price', 'selling_price', 'name', 'min_stock_level']);

        $product->update($data);

        $changes = [];
        foreach (['purchase_price', 'selling_price'] as $field) {
            if ((string) $original[$field] !== (string) $product->{$field}) {
                $changes[$field] = ['from' => $original[$field], 'to' => $product->{$field}];
            }
        }

        $this->logActivity(
            $changes ? 'price_updated' : 'updated',
            'Product',
            $changes
                ? "Updated prices for {$product->name}"
                : "Updated product {$product->name}",
            $product,
            $changes ?: null,
        );

        return redirect()->route('store.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        if ($product->purchaseItems()->exists() || $product->stockMovements()->exists()) {
            return back()->with('error', 'This product has stock history and cannot be deleted. Deactivate it instead.');
        }

        $name = $product->name;
        $product->delete();

        $this->logActivity('deleted', 'Product', "Deleted product {$name}");

        return redirect()->route('store.products.index')->with('success', 'Product deleted.');
    }

    private function formData(): array
    {
        return [
            'categories' => Category::query()->active()->orderBy('name')->get(),
            'brands' => Brand::query()->active()->orderBy('name')->get(),
            'units' => Unit::query()->active()->orderBy('name')->get(),
        ];
    }

    private function nextSku(): string
    {
        $latest = Product::withTrashed()
            ->where('sku', 'like', 'SKU-%')
            ->orderByDesc('id')
            ->value('sku');

        $sequence = 1;

        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('SKU-%04d', $sequence);
    }
}
