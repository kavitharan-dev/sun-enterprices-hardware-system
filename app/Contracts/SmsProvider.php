<?php

namespace App\Contracts;

use App\Services\Sms\SmsSendResult;

interface SmsProvider
{
    public function send(string $recipient, string $message, string $senderId): SmsSendResult;

    public function viewStatus(string $providerMessageId): SmsSendResult;
}
