<?php

namespace EcomAlo\Laravel\Events;

use EcomAlo\Webhook\WebhookEvent;
use Illuminate\Foundation\Events\Dispatchable;

// every verified webhook fires WebhookReceived, then the class for its event (OrderStatusChanged, ParcelBooked, ...)
class WebhookReceived
{
    use Dispatchable;

    public function __construct(public WebhookEvent $webhook)
    {
    }
}
