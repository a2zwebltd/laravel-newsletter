<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Events;

use A2ZWeb\Newsletter\Contracts\CanReceiveMailing;
use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a mailing email is successfully delivered to a recipient.
 *
 * The host can listen to mirror the newsletter into an in-app notification
 * centre, write analytics, etc. `$recipientModel` is the resolved User or
 * MailingSubscriber the email was sent to (null if it could not be resolved).
 */
class MailingDelivered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Mailing $mailing,
        public readonly MailingRecipient $recipient,
        public readonly ?CanReceiveMailing $recipientModel = null,
    ) {}
}
