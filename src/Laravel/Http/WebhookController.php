<?php

namespace EcomAlo\Laravel\Http;

use EcomAlo\Exceptions\InvalidSignatureException;
use EcomAlo\Laravel\Events;
use EcomAlo\Webhook\WebhookEvent;
use EcomAlo\Webhook\WebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// EcomAlo waits 10 seconds and retries anything that is not 2xx, so heavy listeners should implement ShouldQueue
class WebhookController
{
    private const EVENTS = [
        WebhookEvent::ORDER_CREATED => Events\OrderCreated::class,
        WebhookEvent::ORDER_STATUS_CHANGED => Events\OrderStatusChanged::class,
        WebhookEvent::ORDER_CANCELLED => Events\OrderCancelled::class,
        WebhookEvent::PARCEL_BOOKED => Events\ParcelBooked::class,
        WebhookEvent::PARCEL_STATUS_CHANGED => Events\ParcelStatusChanged::class,
        WebhookEvent::PARCEL_DELIVERED => Events\ParcelDelivered::class,
        WebhookEvent::PARCEL_RETURNED => Events\ParcelReturned::class,
        WebhookEvent::STOCK_CHANGED => Events\StockChanged::class,
    ];

    public function __invoke(Request $request, WebhookVerifier $verifier): JsonResponse
    {
        try {
            $webhook = $verifier->verify($request->getContent(), $request->headers->all());
        } catch (InvalidSignatureException $e) {
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        $minutes = (int) config('ecomalo.webhook.dedupe_minutes', 1440);

        if ($minutes > 0 && $webhook->id !== '' && ! Cache::add('ecomalo:webhook:'.$webhook->id, true, now()->addMinutes($minutes))) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        try {
            event(new Events\WebhookReceived($webhook));

            if (isset(self::EVENTS[$webhook->event])) {
                $class = self::EVENTS[$webhook->event];
                event(new $class($webhook));
            }
        } catch (\Throwable $e) {
            // a failed listener lets EcomAlo try the same delivery again
            if ($minutes > 0 && $webhook->id !== '') {
                Cache::forget('ecomalo:webhook:'.$webhook->id);
            }

            throw $e;
        }

        return response()->json(['ok' => true]);
    }
}
