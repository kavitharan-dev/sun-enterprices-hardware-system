<?php

namespace App\Services;

use App\Contracts\SmsProvider;
use App\Enums\SmsStatus;
use App\Jobs\SendSmsJob;
use App\Models\SmsLog;
use App\Services\Sms\NullSmsProvider;
use App\Services\Sms\TextLkSmsProvider;
use Illuminate\Database\Eloquent\Model;

class SmsService
{
    public function queue(
        string $recipient,
        string $message,
        string $eventType,
        ?Model $related = null,
    ): SmsLog {
        $normalized = $this->normalizeRecipient($recipient);

        $log = SmsLog::query()->create([
            'recipient' => $normalized,
            'message' => $message,
            'provider' => (string) config('sms.provider', 'textlk'),
            'event_type' => $eventType,
            'status' => config('sms.enabled') ? SmsStatus::Queued : SmsStatus::Skipped,
            'error_message' => config('sms.enabled') ? null : 'SMS_ENABLED is false.',
            'related_type' => $related ? $related::class : null,
            'related_id' => $related?->getKey(),
        ]);

        if (config('sms.enabled')) {
            SendSmsJob::dispatch($log->id);
        }

        return $log;
    }

    public function sendNow(SmsLog $log): SmsLog
    {
        if (in_array($log->status, [SmsStatus::Sent, SmsStatus::Delivered, SmsStatus::Skipped], true)) {
            return $log;
        }

        $log->update([
            'status' => SmsStatus::Sending,
            'attempts' => $log->attempts + 1,
        ]);

        $result = $this->provider()->send(
            $log->recipient,
            $log->message,
            (string) config('sms.sender_id'),
        );

        if ($result->success) {
            $log->update([
                'status' => SmsStatus::Sent,
                'provider_message_id' => $result->providerMessageId,
                'payload' => $result->raw,
                'error_message' => null,
                'sent_at' => now(),
                'next_retry_at' => null,
            ]);

            return $log->fresh();
        }

        $max = (int) config('sms.max_attempts', 5);
        $retryAt = $log->attempts < $max
            ? now()->addMinutes((int) config('sms.retry_after_minutes', 15))
            : null;

        $log->update([
            'status' => SmsStatus::Failed,
            'error_message' => $result->error,
            'payload' => $result->raw,
            'next_retry_at' => $retryAt,
        ]);

        return $log->fresh();
    }

    public function retryFailed(): int
    {
        $logs = SmsLog::query()
            ->where('status', SmsStatus::Failed)
            ->where('attempts', '<', (int) config('sms.max_attempts', 5))
            ->where(function ($query) {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->limit(50)
            ->get();

        foreach ($logs as $log) {
            SendSmsJob::dispatch($log->id);
        }

        return $logs->count();
    }

    public function refreshDeliveryStatuses(): int
    {
        $logs = SmsLog::query()
            ->where('status', SmsStatus::Sent)
            ->whereNotNull('provider_message_id')
            ->where('sent_at', '>=', now()->subDays(2))
            ->limit(50)
            ->get();

        foreach ($logs as $log) {
            $result = $this->provider()->viewStatus((string) $log->provider_message_id);

            if (! $result->success) {
                continue;
            }

            $status = strtolower((string) $result->status);

            if (in_array($status, ['delivered', 'delivery', 'success'], true)) {
                $log->update([
                    'status' => SmsStatus::Delivered,
                    'delivered_at' => now(),
                    'payload' => $result->raw,
                ]);
            }
        }

        return $logs->count();
    }

    public function normalizeRecipient(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '94') && strlen($digits) >= 11) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '94'.substr($digits, 1);
        }

        return $digits;
    }

    public function provider(): SmsProvider
    {
        if (! config('sms.enabled')) {
            return app(NullSmsProvider::class);
        }

        return match (config('sms.provider')) {
            'textlk' => app(TextLkSmsProvider::class),
            default => app(NullSmsProvider::class),
        };
    }
}
