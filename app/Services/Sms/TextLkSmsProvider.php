<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TextLkSmsProvider implements SmsProvider
{
    public function send(string $recipient, string $message, string $senderId): SmsSendResult
    {
        $apiKey = (string) config('sms.api_key');

        if ($apiKey === '') {
            return new SmsSendResult(false, error: 'SMS_API_KEY is not configured.');
        }

        try {
            $response = Http::timeout((int) config('sms.timeout', 20))
                ->acceptJson()
                ->withToken($apiKey)
                ->post((string) config('sms.endpoint'), [
                    'recipient' => $recipient,
                    'sender_id' => $senderId,
                    'type' => 'plain',
                    'message' => $message,
                ]);

            $payload = $response->json() ?? [];

            if ($response->failed() || ($payload['status'] ?? false) !== true) {
                return new SmsSendResult(
                    success: false,
                    error: $payload['message'] ?? ('HTTP '.$response->status()),
                    raw: $this->safePayload($payload),
                );
            }

            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            return new SmsSendResult(
                success: true,
                providerMessageId: $data['sms_id'] ?? $data['uid'] ?? $data['id'] ?? null,
                status: 'sent',
                raw: $this->safePayload($payload),
            );
        } catch (RequestException $e) {
            Log::error('Text.lk SMS request failed.', ['message' => $e->getMessage()]);

            return new SmsSendResult(false, error: 'SMS provider request failed.');
        } catch (\Throwable $e) {
            Log::error('Text.lk SMS unexpected error.', ['message' => $e->getMessage()]);

            return new SmsSendResult(false, error: 'SMS provider unavailable.');
        }
    }

    public function viewStatus(string $providerMessageId): SmsSendResult
    {
        $apiKey = (string) config('sms.api_key');
        $base = rtrim((string) config('sms.view_endpoint'), '/');

        try {
            $response = Http::timeout((int) config('sms.timeout', 20))
                ->acceptJson()
                ->withToken($apiKey)
                ->post($base.'/'.$providerMessageId);

            $payload = $response->json() ?? [];
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
            $status = strtolower((string) ($data['status'] ?? $payload['message_status'] ?? 'sent'));

            return new SmsSendResult(
                success: $response->successful(),
                providerMessageId: $providerMessageId,
                status: $status,
                error: $response->successful() ? null : ($payload['message'] ?? 'Unable to fetch delivery status'),
                raw: $this->safePayload($payload),
            );
        } catch (\Throwable $e) {
            Log::warning('Text.lk status lookup failed.', ['message' => $e->getMessage()]);

            return new SmsSendResult(false, $providerMessageId, error: 'Status lookup failed.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function safePayload(array $payload): array
    {
        unset($payload['api_token'], $payload['api_key'], $payload['token']);

        return $payload;
    }
}
