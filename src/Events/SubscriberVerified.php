<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Events;

use A2ZWeb\Newsletter\Models\MailingSubscriber;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired the first time a subscriber confirms their address through the
 * verification link. Listen to send a welcome mail, sync a CRM, etc.
 */
class SubscriberVerified
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly MailingSubscriber $subscriber) {}
}
