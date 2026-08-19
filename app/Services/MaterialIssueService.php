<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\MaterialIssueStatus;
use App\Enums\MaterialRequestStatus;
use App\Enums\MovementType;
use App\Models\MaterialIssue;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\ProjectExpense;
use App\Notifications\InAppAlert;
use App\Traits\LogsActivity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MaterialIssueService
{
    use LogsActivity;

    public function __construct(
        private readonly StockService $stockService,
        private readonly DocumentNumberService $documentNumbers,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array<int, array{id:int, quantity:float|string}>  $itemIssues
     */
    public function issueFromRequest(MaterialRequest $request, array $itemIssues, int $userId, array $meta = []): MaterialIssue
    {
        if (! $request->canIssue()) {
            throw new RuntimeException('Only approved material requests can be issued.');
        }

        return DB::transaction(function () use ($request, $itemIssues, $userId, $meta) {
            $request->load(['items.product', 'project.siteManager', 'requester']);
            $quantities = collect($itemIssues)->keyBy(fn (array $row) => (int) $row['id']);
            $lines = [];

            foreach ($request->items as $item) {
                $qty = round((float) ($quantities[$item->id]['quantity'] ?? 0), 3);

                if ($qty <= 0) {
                    continue;
                }

                $remaining = $item->remainingToIssue();

                if ($qty > $remaining) {
                    throw new RuntimeException("Cannot issue more than the remaining approved quantity for {$item->product?->name}. Remaining: {$remaining}.");
                }

                if ((float) $item->product->stock_quantity < $qty) {
                    throw new RuntimeException("Insufficient stock for {$item->product->name}. Available: {$item->product->stock_quantity}.");
                }

                $lines[] = ['item' => $item, 'quantity' => $qty];
            }

            if ($lines === []) {
                throw new RuntimeException('Enter at least one quantity to issue.');
            }

            $issue = MaterialIssue::query()->create([
                'issue_no' => $this->documentNumbers->next('material_issue_prefix', 'MI', MaterialIssue::class, 'issue_no'),
                'project_id' => $request->project_id,
                'material_request_id' => $request->id,
                'issue_date' => $meta['issue_date'] ?? now()->toDateString(),
                'issued_by' => $userId,
                'total_cost' => 0,
                'notes' => $meta['notes'] ?? null,
                'status' => MaterialIssueStatus::Completed,
            ]);

            $total = 0;

            foreach ($lines as $line) {
                /** @var MaterialRequestItem $item */
                $item = $line['item'];
                $qty = $line['quantity'];
                $unitCost = round((float) $item->product->purchase_price, 2);
                $subtotal = round($qty * $unitCost, 2);
                $total += $subtotal;

                $issue->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'subtotal' => $subtotal,
                    'material_request_item_id' => $item->id,
                ]);

                $this->stockService->record(
                    product: $item->product,
                    type: MovementType::MaterialIssueOut,
                    quantity: $qty,
                    unitCost: $unitCost,
                    reference: $issue,
                    notes: "Issued to {$request->project->name} ({$request->request_no})",
                    movementDate: $issue->issue_date,
                    userId: $userId,
                );

                $item->update([
                    'quantity_issued' => round((float) $item->quantity_issued + $qty, 3),
                ]);
            }

            $issue->update(['total_cost' => round($total, 2)]);
            $this->refreshRequestStatus($request);

            $issue->load('items.product.unit');
            $products = $issue->itemsSummary();

            ProjectExpense::query()->create([
                'project_id' => $request->project_id,
                'category' => ExpenseCategory::Material,
                'amount' => round($total, 2),
                'expense_date' => $issue->issue_date,
                'description' => trim("{$issue->issue_no} for {$request->request_no}".($products !== '' ? " — {$products}" : '')),
                'reference_type' => $issue::class,
                'reference_id' => $issue->id,
                'created_by' => $userId,
            ]);

            $this->logActivity(
                'issued',
                'MaterialIssue',
                "Issued {$issue->issue_no} from {$request->request_no} — stock reduced",
                $issue,
            );

            $summary = "{$issue->issue_no}: materials issued to {$request->project->name} (Rs. ".number_format($total, 2).').';
            $this->notifications->materialIssued(
                $issue,
                $summary,
                $request->requester?->phone ?? $request->project->siteManager?->phone,
            );

            if ($request->requester) {
                $request->requester->notify(new InAppAlert(
                    'Materials issued',
                    $summary,
                    'material_issued',
                    route('construction.material-requests.show', $request),
                ));
            }

            return $issue->fresh(['items.product.unit', 'project', 'materialRequest', 'issuer']);
        });
    }

    private function refreshRequestStatus(MaterialRequest $request): void
    {
        $request->load('items');

        $hasIssued = $request->items->contains(fn (MaterialRequestItem $item) => (float) $item->quantity_issued > 0);
        $remaining = $request->items->sum(fn (MaterialRequestItem $item) => $item->remainingToIssue());

        $status = match (true) {
            $remaining <= 0 && $hasIssued => MaterialRequestStatus::Issued,
            $hasIssued => MaterialRequestStatus::PartiallyIssued,
            default => $request->status,
        };

        $request->update(['status' => $status]);
    }
}
