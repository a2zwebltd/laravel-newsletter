<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Events;

use A2ZWeb\Newsletter\Models\MailingSubscriber;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an anonymous subscriber unsubscribes (through the confirmation
 * page or the RFC 8058 one-click endpoint). In 'delete' mode the model is
 * already deleted when the event fires.
 */
class SubscriberUnsubscribed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly MailingSubscriber $subscriber) {}
}
