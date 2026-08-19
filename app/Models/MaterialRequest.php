<?php

namespace App\Models;

use App\Enums\MaterialRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'request_no',
        'project_id',
        'requested_by',
        'request_date',
        'required_date',
        'status',
        'notes',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'required_date' => 'date',
            'status' => MaterialRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequestItem::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(MaterialIssue::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin') || $user->canReviewMaterialRequests()) {
            return $query;
        }

        if ($user->hasRole('site_manager')) {
            return $query->where(function (Builder $inner) use ($user) {
                $inner->where('requested_by', $user->id)
                    ->orWhereHas('project', fn (Builder $project) => $project->where('site_manager_id', $user->id));
            });
        }

        return $query->whereRaw('0 = 1');
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', MaterialRequestStatus::Pending);
    }

    public function isDraft(): bool
    {
        return $this->status === MaterialRequestStatus::Draft;
    }

    public function isPending(): bool
    {
        return $this->status === MaterialRequestStatus::Pending;
    }

    public function isApproved(): bool
    {
        return in_array($this->status, [
            MaterialRequestStatus::Approved,
            MaterialRequestStatus::PartiallyApproved,
            MaterialRequestStatus::PartiallyIssued,
            MaterialRequestStatus::Issued,
        ], true);
    }

    public function canIssue(): bool
    {
        return in_array($this->status, [
            MaterialRequestStatus::Approved,
            MaterialRequestStatus::PartiallyApproved,
            MaterialRequestStatus::PartiallyIssued,
        ], true);
    }

    public function isFullyIssued(): bool
    {
        return $this->status === MaterialRequestStatus::Issued;
    }

    public function itemsSummary(int $limit = 6): string
    {
        $this->loadMissing('items.product.unit');

        $summary = \App\Support\ItemLineSummary::from($this->items, 'quantity_requested', $limit);
        $notes = trim((string) $this->notes);

        if ($notes !== '') {
            return $summary !== '' ? $summary.' — '.$notes : $notes;
        }

        return $summary;
    }
}
