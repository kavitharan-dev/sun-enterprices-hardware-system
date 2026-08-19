<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\MaterialIssue;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * @return array{from: string, to: string}
     */
    public function dateRange(?string $from, ?string $to): array
    {
        $start = $from ? Carbon::parse($from)->toDateString() : now()->startOfMonth()->toDateString();
        $end = $to ? Carbon::parse($to)->toDateString() : now()->toDateString();

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return ['from' => $start, 'to' => $end];
    }

    /**
     * @return array<string, mixed>
     */
    public function sales(string $from, string $to): array
    {
        $sales = Sale::query()
            ->with('customer')
            ->where('status', SaleStatus::Completed)
            ->whereDate('sale_date', '>=', $from)
            ->whereDate('sale_date', '<=', $to)
            ->latest('sale_date')
            ->get();

        $byProduct = SaleItem::query()
            ->selectRaw('product_id, SUM(quantity) as quantity, SUM(subtotal) as total')
            ->whereHas('sale', function ($query) use ($from, $to) {
                $query->where('status', SaleStatus::Completed)
                    ->whereDate('sale_date', '>=', $from)
                    ->whereDate('sale_date', '<=', $to);
            })
            ->groupBy('product_id')
            ->with('product.unit')
            ->get();

        return [
            'rows' => $sales,
            'by_product' => $byProduct,
            'total' => (float) $sales->sum('total'),
            'paid' => (float) $sales->sum('paid_amount'),
            'balance' => (float) $sales->sum('balance'),
            'count' => $sales->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function purchases(string $from, string $to): array
    {
        $purchases = Purchase::query()
            ->with('supplier')
            ->where('status', PurchaseStatus::Completed)
            ->whereDate('purchase_date', '>=', $from)
            ->whereDate('purchase_date', '<=', $to)
            ->latest('purchase_date')
            ->get();

        return [
            'rows' => $purchases,
            'total' => (float) $purchases->sum('total'),
            'count' => $purchases->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inventory(): array
    {
        $products = Product::query()
            ->with(['category', 'unit'])
            ->orderBy('name')
            ->get();

        $stockValue = $products->sum(fn (Product $product) => (float) $product->stock_quantity * (float) $product->purchase_price);

        return [
            'rows' => $products,
            'stock_value' => round($stockValue, 2),
            'low_stock' => $products->filter(fn (Product $product) => $product->isLowStock())->count(),
        ];
    }

    /**
     * @return Collection<int, StockMovement>
     */
    public function stockMovements(string $from, string $to): Collection
    {
        return StockMovement::query()
            ->with(['product.unit', 'user'])
            ->whereDate('movement_date', '>=', $from)
            ->whereDate('movement_date', '<=', $to)
            ->latest('movement_date')
            ->latest('id')
            ->limit(500)
            ->get();
    }

    /**
     * @return Collection<int, Sale>
     */
    public function outstanding(): Collection
    {
        return Sale::query()
            ->with('customer')
            ->where('status', SaleStatus::Completed)
            ->where('balance', '>', 0)
            ->orderByDesc('balance')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function projects(User $user): array
    {
        $projects = Project::query()
            ->visibleTo($user)
            ->with(['customer', 'siteManager'])
            ->withSum('expenses as spent_total', 'amount')
            ->orderBy('name')
            ->get();

        return [
            'rows' => $projects,
            'budget' => (float) $projects->sum('budget'),
            'spent' => (float) $projects->sum('spent_total'),
        ];
    }

    /**
     * @return Collection<int, ProjectExpense>
     */
    public function expenses(User $user, string $from, string $to): Collection
    {
        return ProjectExpense::query()
            ->visibleTo($user)
            ->with(['project', 'creator'])
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->latest('expense_date')
            ->get();
    }

    /**
     * @return Collection<int, MaterialIssue>
     */
    public function materialIssues(User $user, string $from, string $to): Collection
    {
        return MaterialIssue::query()
            ->with(['project', 'issuer', 'items.product.unit'])
            ->when(
                $user->hasRole('site_manager') && ! $user->hasRole('admin'),
                fn ($query) => $query->whereHas('project', fn ($project) => $project->where('site_manager_id', $user->id)),
            )
            ->whereDate('issue_date', '>=', $from)
            ->whereDate('issue_date', '<=', $to)
            ->latest('issue_date')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function salesTrend(int $days = 14): Collection
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $totals = Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereDate('sale_date', '>=', $start->toDateString())
            ->selectRaw('sale_date as day, SUM(total) as total')
            ->groupBy('sale_date')
            ->pluck('total', 'day');

        return collect(range(0, $days - 1))->map(function (int $offset) use ($start, $totals) {
            $date = $start->copy()->addDays($offset);

            return (object) [
                'date' => $date->toDateString(),
                'label' => $date->format('d/m'),
                'total' => (float) ($totals[$date->toDateString()] ?? $totals[$date->format('Y-m-d')] ?? 0),
            ];
        });
    }
}
