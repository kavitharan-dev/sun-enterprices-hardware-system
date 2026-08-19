<?php

namespace App\Http\Controllers\Store;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StockAdjustmentRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockService;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class InventoryController extends Controller
{
    use LogsActivity;

    public function __construct(private readonly StockService $stockService) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->canManageStore(), 403);

        $products = Product::query()
            ->with(['category', 'unit'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)->orWhere('sku', 'like', $term);
                });
            })
            ->when($request->boolean('low_stock'), fn ($query) => $query->lowStock())
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $lowStockCount = Product::query()->lowStock()->count();
        $adjustmentProducts = Product::query()->orderBy('name')->get(['id', 'name', 'sku']);

        return view('store.inventory.index', compact('products', 'lowStockCount', 'adjustmentProducts'));
    }

    public function movements(Request $request): View
    {
        abort_unless($request->user()->canManageStore(), 403);

        $movements = StockMovement::query()
            ->with(['product.unit', 'user'])
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('movement_type', $request->string('type')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('movement_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('movement_date', '<=', $request->date('to')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $products = Product::query()->orderBy('name')->get(['id', 'name', 'sku']);

        return view('store.inventory.movements', compact('movements', 'products'));
    }

    public function adjust(StockAdjustmentRequest $request): RedirectResponse
    {
        $product = Product::query()->findOrFail($request->integer('product_id'));
        $type = $request->input('direction') === 'in'
            ? MovementType::AdjustmentIn
            : MovementType::AdjustmentOut;

        try {
            $this->stockService->record(
                product: $product,
                type: $type,
                quantity: (float) $request->input('quantity'),
                unitCost: (float) $product->purchase_price,
                notes: $request->string('notes')->toString(),
                movementDate: $request->date('movement_date'),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $this->logActivity(
            'adjusted',
            'Inventory',
            "Stock {$type->label()} for {$product->name}: {$request->input('quantity')}",
            $product,
        );

        return back()->with('success', 'Stock adjustment recorded.');
    }
}
