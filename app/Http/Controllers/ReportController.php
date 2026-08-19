<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->canViewReports(), 403);

        return view('reports.index');
    }

    public function sales(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()?->canViewStoreReports(), 403);

        $range = $this->reports->dateRange($request->input('from'), $request->input('to'));
        $data = $this->reports->sales($range['from'], $range['to']);

        if ($request->boolean('export')) {
            return $this->csv('sales-report.csv', ['Date', 'Invoice', 'Customer', 'Total', 'Paid', 'Balance'], $data['rows']->map(fn ($sale) => [
                $sale->sale_date->toDateString(),
                $sale->invoice_no,
                $sale->customerName(),
                number_format((float) $sale->total, 2, '.', ''),
                number_format((float) $sale->paid_amount, 2, '.', ''),
                number_format((float) $sale->balance, 2, '.', ''),
            ]));
        }

        return view('reports.sales', [...$range, ...$data]);
    }

    public function purchases(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()?->canViewStoreReports(), 403);

        $range = $this->reports->dateRange($request->input('from'), $request->input('to'));
        $data = $this->reports->purchases($range['from'], $range['to']);

        if ($request->boolean('export')) {
            return $this->csv('purchases-report.csv', ['Date', 'Reference', 'Supplier', 'Total'], $data['rows']->map(fn ($purchase) => [
                $purchase->purchase_date->toDateString(),
                $purchase->reference_no,
                $purchase->supplier?->name,
                number_format((float) $purchase->total, 2, '.', ''),
            ]));
        }

        return view('reports.purchases', [...$range, ...$data]);
    }

    public function inventory(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()?->canViewStoreReports(), 403);

        $data = $this->reports->inventory();

        if ($request->boolean('export')) {
            return $this->csv('inventory-report.csv', ['SKU', 'Product', 'Category', 'Qty', 'Min', 'Value'], $data['rows']->map(fn ($product) => [
                $product->sku,
                $product->name,
                $product->category?->name,
                number_format((float) $product->stock_quantity, 3, '.', ''),
                number_format((float) $product->min_stock_level, 3, '.', ''),
                number_format((float) $product->stock_quantity * (float) $product->purchase_price, 2, '.', ''),
            ]));
        }

        return view('reports.inventory', $data);
    }

    public function movements(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()?->canViewStoreReports(), 403);

        $range = $this->reports->dateRange($request->input('from'), $request->input('to'));
        $rows = $this->reports->stockMovements($range['from'], $range['to']);

        if ($request->boolean('export')) {
            return $this->csv('stock-movements.csv', ['Date', 'Product', 'Type', 'Qty', 'Balance', 'User'], $rows->map(fn ($movement) => [
                $movement->movement_date->toDateString(),
                $movement->product?->name,
                $movement->movement_type->value,
                number_format((float) $movement->quantity, 3, '.', ''),
                number_format((float) $movement->balance_after, 3, '.', ''),
                $movement->user?->name,
            ]));
        }

        return view('reports.movements', [...$range, 'rows' => $rows]);
    }

    public function outstanding(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()?->canViewStoreReports(), 403);

        $rows = $this->reports->outstanding();

        if ($request->boolean('export')) {
            return $this->csv('outstanding-payments.csv', ['Invoice', 'Customer', 'Date', 'Total', 'Balance'], $rows->map(fn ($sale) => [
                $sale->invoice_no,
                $sale->customerName(),
                $sale->sale_date->toDateString(),
                number_format((float) $sale->total, 2, '.', ''),
                number_format((float) $sale->balance, 2, '.', ''),
            ]));
        }

        return view('reports.outstanding', [
            'rows' => $rows,
            'total' => (float) $rows->sum('balance'),
        ]);
    }

    public function projects(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()?->canViewConstructionReports(), 403);

        $data = $this->reports->projects($request->user());

        if ($request->boolean('export')) {
            return $this->csv('projects-report.csv', ['Code', 'Project', 'Budget', 'Received', 'Still to receive', 'Spent', 'Cash balance', 'Progress'], $data['rows']->map(fn ($project) => [
                $project->project_code,
                $project->name,
                number_format((float) $project->budget, 2, '.', ''),
                number_format((float) $project->received_total, 2, '.', ''),
                number_format((float) $project->budget - (float) $project->received_total, 2, '.', ''),
                number_format((float) $project->spent_total, 2, '.', ''),
                number_format((float) $project->received_total - (float) $project->spent_total, 2, '.', ''),
                number_format((float) $project->progress_percentage, 1, '.', ''),
            ]));
        }

        return view('reports.projects', $data);
    }

    public function expenses(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()?->canViewConstructionReports(), 403);

        $range = $this->reports->dateRange($request->input('from'), $request->input('to'));
        $rows = $this->reports->expenses($request->user(), $range['from'], $range['to']);

        if ($request->boolean('export')) {
            return $this->csv('project-expenses.csv', ['Date', 'Project', 'Category', 'Amount', 'Description'], $rows->map(fn ($expense) => [
                $expense->expense_date->toDateString(),
                $expense->project?->name,
                $expense->category->value,
                number_format((float) $expense->amount, 2, '.', ''),
                $expense->description,
            ]));
        }

        return view('reports.expenses', [...$range, 'rows' => $rows, 'total' => (float) $rows->sum('amount')]);
    }

    public function issues(Request $request): View|StreamedResponse
    {
        abort_unless($request->user()?->canViewConstructionReports(), 403);

        $range = $this->reports->dateRange($request->input('from'), $request->input('to'));
        $rows = $this->reports->materialIssues($request->user(), $range['from'], $range['to']);

        if ($request->boolean('export')) {
            return $this->csv('material-issues.csv', ['Date', 'Issue', 'Project', 'Cost'], $rows->map(fn ($issue) => [
                $issue->issue_date->toDateString(),
                $issue->issue_no,
                $issue->project?->name,
                number_format((float) $issue->total_cost, 2, '.', ''),
            ]));
        }

        return view('reports.issues', [...$range, 'rows' => $rows, 'total' => (float) $rows->sum('total_cost')]);
    }

    /**
     * @param  Collection<int, array<int, mixed>>  $rows
     */
    private function csv(string $filename, array $headers, Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
