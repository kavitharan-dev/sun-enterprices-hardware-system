<?php

namespace App\Jobs;

use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryFailedSmsJob implements ShouldQueue
{
    use Queueable;

    public function handle(SmsService $sms): void
    {
        $sms->retryFailed();
        $sms->refreshDeliveryStatuses();
    }
}
