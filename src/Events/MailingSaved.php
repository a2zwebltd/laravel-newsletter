<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Events;

use A2ZWeb\Newsletter\Models\Mailing;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a Mailing is saved. Listen to react to CTA changes
 * (link shortening), cache invalidation, search indexing, etc.
 */
class MailingSaved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Mailing $mailing) {}
}
