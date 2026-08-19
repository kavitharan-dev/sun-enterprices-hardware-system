<?php

namespace App\Services\Sms;

class SmsSendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $status = null,
        public readonly ?string $error = null,
        public readonly array $raw = [],
    ) {}
}
