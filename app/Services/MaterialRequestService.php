<?php

namespace App\Services;

use App\Enums\MaterialRequestStatus;
use App\Models\MaterialRequest;
use App\Models\Product;
use App\Models\Project;
use App\Notifications\InAppAlert;
use App\Traits\LogsActivity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MaterialRequestService
{
    use LogsActivity;

    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly NotificationService $notifications,
    ) {}

    public function create(array $data, array $items, int $userId): MaterialRequest
    {
        $this->assertProjectAccess($data['project_id'], $userId);

        return DB::transaction(function () use ($data, $items, $userId) {
            $request = MaterialRequest::query()->create([
                'request_no' => $this->documentNumbers->next('material_request_prefix', 'MR', MaterialRequest::class, 'request_no'),
                'project_id' => $data['project_id'],
                'requested_by' => $userId,
                'request_date' => $data['request_date'],
                'required_date' => $data['required_date'] ?: null,
                'status' => MaterialRequestStatus::Draft,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($request, $items);
            $this->logActivity('created', 'MaterialRequest', "Created material request {$request->request_no}", $request);

            return $request->fresh(['items', 'project']);
        });
    }

    public function update(MaterialRequest $request, array $data, array $items, int $userId): MaterialRequest
    {
        if (! $request->isDraft()) {
            throw new RuntimeException('Only draft material requests can be updated.');
        }

        $this->assertProjectAccess($data['project_id'], $userId);

        return DB::transaction(function () use ($request, $data, $items) {
            $request->update([
                'project_id' => $data['project_id'],
                'request_date' => $data['request_date'],
                'required_date' => $data['required_date'] ?: null,
                'notes' => $data['notes'] ?? null,
            ]);

            $request->items()->delete();
            $this->syncItems($request, $items);
            $this->logActivity('updated', 'MaterialRequest', "Updated material request {$request->request_no}", $request);

            return $request->fresh(['items', 'project']);
        });
    }

    public function submit(MaterialRequest $request): MaterialRequest
    {
        if (! $request->isDraft()) {
            throw new RuntimeException('Only draft material requests can be submitted.');
        }

        if ($request->items()->count() === 0) {
            throw new RuntimeException('Add at least one product before submitting this request.');
        }

        $request->update(['status' => MaterialRequestStatus::Pending]);
        $request->load(['project.siteManager', 'requester']);

        $this->logActivity('submitted', 'MaterialRequest', "Submitted material request {$request->request_no}", $request);

        $summary = "{$request->request_no} for {$request->project->name} is waiting for store approval.";
        $this->notifications->materialRequestSubmitted(
            $request,
            $summary,
            route('store.material-requests.show', $request),
        );

        return $request;
    }

    /**
     * @param  array<int, array{id:int, quantity_approved:float|string}>  $itemApprovals
     */
    public function approve(MaterialRequest $request, array $itemApprovals, int $reviewerId): MaterialRequest
    {
        if (! $request->isPending()) {
            throw new RuntimeException('Only pending material requests can be approved.');
        }

        if ($request->requested_by === $reviewerId) {
            throw new RuntimeException('You cannot approve your own material request.');
        }

        return DB::transaction(function () use ($request, $itemApprovals, $reviewerId) {
            $request->load('items');
            $approvals = collect($itemApprovals)->keyBy(fn (array $row) => (int) $row['id']);
            $approvedCount = 0;
            $partial = false;

            foreach ($request->items as $item) {
                $qty = round((float) ($approvals[$item->id]['quantity_approved'] ?? 0), 3);

                if ($qty < 0 || $qty > (float) $item->quantity_requested) {
                    throw new RuntimeException("Approved quantity for {$item->product?->name} must be between 0 and {$item->quantity_requested}.");
                }

                $item->update(['quantity_approved' => $qty]);

                if ($qty > 0) {
                    $approvedCount++;
                }

                if ($qty < (float) $item->quantity_requested) {
                    $partial = true;
                }
            }

            if ($approvedCount === 0) {
                throw new RuntimeException('Approve at least one product, or reject the request instead.');
            }

            $status = $partial
                ? MaterialRequestStatus::PartiallyApproved
                : MaterialRequestStatus::Approved;

            $request->update([
                'status' => $status,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->logActivity(
                'approved',
                'MaterialRequest',
                "Approved material request {$request->request_no}",
                $request,
            );

            $request->load(['project.siteManager', 'requester']);
            $summary = "{$request->request_no} for {$request->project->name} was {$status->label()}.";
            $this->notifications->materialRequestApproved(
                $request,
                $summary,
                $request->requester?->phone ?? $request->project->siteManager?->phone,
            );

            if ($request->requester) {
                $request->requester->notify(new InAppAlert(
                    'Material request approved',
                    $summary,
                    'material_request_approved',
                    route('construction.material-requests.show', $request),
                ));
            }

            return $request->fresh(['items.product', 'project']);
        });
    }

    public function reject(MaterialRequest $request, string $reason, int $reviewerId): MaterialRequest
    {
        if (! $request->isPending()) {
            throw new RuntimeException('Only pending material requests can be rejected.');
        }

        if ($request->requested_by === $reviewerId) {
            throw new RuntimeException('You cannot reject your own material request.');
        }

        $request->items()->update(['quantity_approved' => 0]);
        $request->update([
            'status' => MaterialRequestStatus::Rejected,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->logActivity('rejected', 'MaterialRequest', "Rejected material request {$request->request_no}", $request);

        return $request;
    }

    public function cancel(MaterialRequest $request): MaterialRequest
    {
        if (! $request->isDraft()) {
            throw new RuntimeException('Only draft material requests can be cancelled.');
        }

        $request->delete();
        $this->logActivity('cancelled', 'MaterialRequest', "Cancelled draft {$request->request_no}", $request);

        return $request;
    }

    private function syncItems(MaterialRequest $request, array $items): void
    {
        foreach ($items as $item) {
            Product::query()->findOrFail($item['product_id']);

            $request->items()->create([
                'product_id' => $item['product_id'],
                'quantity_requested' => round((float) $item['quantity'], 3),
                'quantity_approved' => 0,
                'quantity_issued' => 0,
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    private function assertProjectAccess(int $projectId, int $userId): void
    {
        $project = Project::query()->findOrFail($projectId);
        $user = \App\Models\User::query()->findOrFail($userId);

        if ($user->hasRole('admin')) {
            return;
        }

        if (! $project->isAssignedTo($user)) {
            throw new RuntimeException('You can only request materials for projects assigned to you.');
        }
    }
}
