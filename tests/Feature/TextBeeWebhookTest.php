<?php

use App\Models\SmsMessage;
use App\Services\SettingsService;

beforeEach(function () {
    app(SettingsService::class)->set('textbee_webhook_secret', 'webhook-secret');
});

test('a signed TextBee event updates delivery state', function () {
    $message = SmsMessage::factory()->create([
        'sms_batch_id' => 'batch-123',
        'recipient' => '+15550102',
        'status' => 'queued',
    ]);

    $body = json_encode([
        'webhookEvent' => 'MESSAGE_DELIVERED',
        'smsBatchId' => 'batch-123',
        'recipient' => '+1 555 0102',
        'status' => 'delivered',
    ], JSON_THROW_ON_ERROR);

    $timestamp = (string) now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'webhook-secret');

    $this->call(
        'POST',
        '/webhooks/textbee',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TEXTBEE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ],
        $body,
    )->assertOk();

    expect($message->refresh())
        ->status->toBe('delivered')
        ->delivered_at->not->toBeNull();
});

test('an invalid TextBee signature is rejected', function () {
    $message = SmsMessage::factory()->create([
        'sms_batch_id' => 'batch-123',
        'status' => 'queued',
    ]);

    $this->postJson('/webhooks/textbee', [
        'smsBatchId' => 'batch-123',
        'recipient' => $message->recipient,
        'status' => 'delivered',
    ], [
        'X-Textbee-Signature' => 't='.now()->timestamp.',v1=wrong',
    ])->assertUnauthorized();

    expect($message->refresh()->status)->toBe('queued');
});

test('a failed TextBee event records the error', function () {
    $message = SmsMessage::factory()->create([
        'sms_batch_id' => 'batch-123',
        'status' => 'queued',
    ]);

    $body = json_encode([
        'webhookEvent' => 'MESSAGE_FAILED',
        'smsBatchId' => 'batch-123',
        'recipient' => $message->recipient,
        'status' => 'failed',
        'error' => 'Carrier rejected the message.',
    ], JSON_THROW_ON_ERROR);

    $timestamp = (string) now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'webhook-secret');

    $this->call('POST', '/webhooks/textbee', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_TEXTBEE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
    ], $body)->assertOk();

    expect($message->refresh())
        ->status->toBe('failed')
        ->error->toBe('Carrier rejected the message.');
});
