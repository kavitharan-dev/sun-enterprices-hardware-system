<?php

namespace App\Http\Controllers;

use App\Enums\MaterialRequestStatus;
use App\Enums\ProjectStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\MaterialRequest;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\Sale;
use App\Models\StoreAssetAssignment;
use App\Models\Supplier;
use App\Services\ReportService;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(ReportService $reports): View
    {
        $user = auth()->user();

        $hasSales = Schema::hasTable('sales');
        $completedSales = $hasSales
            ? Sale::query()->where('status', SaleStatus::Completed)
            : null;

        $stats = [
            'today_sales' => $completedSales
                ? (float) (clone $completedSales)->whereDate('sale_date', today())->sum('total')
                : 0,
            'monthly_sales' => $completedSales
                ? (float) (clone $completedSales)->whereYear('sale_date', now()->year)->whereMonth('sale_date', now()->month)->sum('total')
                : 0,
            'total_products' => Product::query()->count(),
            'low_stock_products' => Product::query()->lowStock()->count(),
            'active_projects' => 0,
            'completed_projects' => 0,
            'total_customers' => Schema::hasTable('customers') ? Customer::query()->count() : 0,
            'total_suppliers' => Supplier::query()->count(),
            'pending_material_requests' => 0,
            'project_expenses' => 0,
            'outstanding_payments' => $completedSales
                ? (float) (clone $completedSales)->sum('balance')
                : 0,
        ];

        if (Schema::hasTable('projects')) {
            $projectQuery = Project::query()->visibleTo($user);
            $stats['active_projects'] = (clone $projectQuery)->where('status', ProjectStatus::Active)->count();
            $stats['completed_projects'] = (clone $projectQuery)->where('status', ProjectStatus::Completed)->count();
        }

        if (Schema::hasTable('material_requests')) {
            $stats['pending_material_requests'] = MaterialRequest::query()
                ->visibleTo($user)
                ->where('status', MaterialRequestStatus::Pending)
                ->count();
        }

        if (Schema::hasTable('project_expenses')) {
            $stats['project_expenses'] = (float) ProjectExpense::query()
                ->visibleTo($user)
                ->sum('amount');
        }

        $salesTrend = $hasSales ? $reports->salesTrend(14) : collect();

        $recentSales = $hasSales
            ? Sale::query()->with('customer')->where('status', SaleStatus::Completed)->latest('sale_date')->limit(6)->get()
            : collect();

        $lowStock = Product::query()
            ->with('unit')
            ->lowStock()
            ->orderBy('stock_quantity')
            ->limit(8)
            ->get();

        $assetsOutNow = Schema::hasTable('store_asset_assignments')
            ? StoreAssetAssignment::query()->whereNull('returned_at')->count()
            : 0;

        if ($user->hasRole('site_manager')) {
            $assignedProjects = Project::query()
                ->visibleTo($user)
                ->with('customer')
                ->latest('id')
                ->limit(8)
                ->get();

            return view('dashboard.site-manager', compact('stats', 'assignedProjects'));
        }

        if ($user->hasRole('cashier') || $user->hasRole('store_manager')) {
            return view('dashboard.cashier', compact('stats', 'lowStock', 'salesTrend', 'assetsOutNow'));
        }

        return view('dashboard.admin', compact('stats', 'lowStock', 'salesTrend', 'recentSales', 'assetsOutNow'));
    }
}
