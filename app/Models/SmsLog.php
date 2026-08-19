<?php

namespace App\Models;

use App\Enums\SmsStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SmsLog extends Model
{
    protected $fillable = [
        'recipient',
        'message',
        'provider',
        'provider_message_id',
        'event_type',
        'status',
        'error_message',
        'attempts',
        'payload',
        'related_type',
        'related_id',
        'sent_at',
        'delivered_at',
        'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SmsStatus::class,
            'payload' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function canRetry(): bool
    {
        return $this->status === SmsStatus::Failed
            && $this->attempts < (int) config('sms.max_attempts', 5);
    }
}
