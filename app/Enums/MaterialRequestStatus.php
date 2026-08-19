<?php

namespace App\Enums;

enum MaterialRequestStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case PartiallyApproved = 'partially_approved';
    case Rejected = 'rejected';
    case PartiallyIssued = 'partially_issued';
    case Issued = 'issued';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::PartiallyApproved => 'Partially approved',
            self::Rejected => 'Rejected',
            self::PartiallyIssued => 'Partially issued',
            self::Issued => 'Issued',
        };
    }

    public function isOpenForReview(): bool
    {
        return $this === self::Pending;
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
