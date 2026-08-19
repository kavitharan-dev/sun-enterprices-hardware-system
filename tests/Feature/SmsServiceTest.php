<?php

namespace Tests\Feature;

use App\Enums\SmsStatus;
use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_sms_is_logged_as_skipped_and_not_queued(): void
    {
        config(['sms.enabled' => false]);
        Queue::fake();

        $log = app(SmsService::class)->queue('0771234567', 'Test message', 'critical_low_stock');

        $this->assertSame(SmsStatus::Skipped, $log->status);
        $this->assertSame('94771234567', $log->recipient);
        Queue::assertNothingPushed();
    }

    public function test_enabled_sms_sends_through_textlk_and_stores_provider_id(): void
    {
        config([
            'sms.enabled' => true,
            'sms.provider' => 'textlk',
            'sms.api_key' => 'test-token',
            'sms.sender_id' => 'SunEnt',
            'sms.endpoint' => 'https://app.text.lk/api/v3/sms/send',
        ]);

        Http::fake([
            'https://app.text.lk/api/v3/sms/send' => Http::response([
                'status' => true,
                'message' => 'SMS queued successfully',
                'data' => ['sms_id' => 'abc123', 'recipient' => '94710000000'],
            ], 200),
        ]);

        $log = app(SmsService::class)->queue('94710000000', 'Low stock alert', 'critical_low_stock');
        $sent = app(SmsService::class)->sendNow($log->fresh());

        $this->assertSame(SmsStatus::Sent, $sent->status);
        $this->assertSame('abc123', $sent->provider_message_id);
        $this->assertNotNull($sent->sent_at);
        $this->assertDatabaseHas('sms_logs', [
            'id' => $sent->id,
            'provider_message_id' => 'abc123',
        ]);
    }
}
