<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Listeners;

use A2ZWeb\Newsletter\Events\MailingSaved;
use Illuminate\Support\Facades\Log;

/**
 * Generates / refreshes the shortened CTA link whenever a mailing's CTA URL
 * changes. Only active when ashallendesign/short-url is installed (the global
 * short_url() helper is present) and short links are enabled in config.
 */
class UpdateMailingShortUrl
{
    public function handle(MailingSaved $event): void
    {
        if (! config('newsletter.short_url.enabled', true) || ! function_exists('short_url')) {
            return;
        }

        $mailing = $event->mailing;

        if (empty($mailing->cta_url)) {
            return;
        }

        if (! $mailing->wasChanged('cta_url') && $mailing->cta_url_short) {
            return;
        }

        try {
            $shortUrl = short_url($mailing->cta_url);

            if ($mailing->cta_url_short !== $shortUrl) {
                $mailing->cta_url_short = $shortUrl;
                $mailing->saveQuietly();
            }
        } catch (\Throwable $e) {
            Log::warning('Newsletter: failed to shorten CTA URL for mailing '.$mailing->getId().': '.$e->getMessage());
        }
    }
}
