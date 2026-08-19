<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;

class NullSmsProvider implements SmsProvider
{
    public function send(string $recipient, string $message, string $senderId): SmsSendResult
    {
        return new SmsSendResult(
            success: false,
            error: 'SMS is disabled or the provider is not configured.',
        );
    }

    public function viewStatus(string $providerMessageId): SmsSendResult
    {
        return new SmsSendResult(false, $providerMessageId, error: 'SMS is disabled.');
    }
}
