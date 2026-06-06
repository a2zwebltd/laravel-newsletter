<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Events;

use A2ZWeb\Newsletter\Models\Mailing;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a Mailing is approved and its recipients have been generated.
 * Listen to submit the public URL to search engines (IndexNow), warm caches, etc.
 */
class MailingApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Mailing $mailing) {}
}
