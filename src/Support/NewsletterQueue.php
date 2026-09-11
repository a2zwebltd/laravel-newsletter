<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Support;

/**
 * Reads the `newsletter.queue.*` settings and applies them to anything using
 * the Illuminate\Bus\Queueable trait (jobs, mailables, queued Nova actions).
 */
final class NewsletterQueue
{
    public static function connection(): ?string
    {
        $connection = config('newsletter.queue.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    public static function name(): ?string
    {
        $name = config('newsletter.queue.name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * Number of attempts for SendMailingJob; null keeps the 24-hour retry window.
     */
    public static function tries(): ?int
    {
        $tries = config('newsletter.queue.tries', 3);

        if ($tries === null || $tries === '' || (int) $tries <= 0) {
            return null;
        }

        return (int) $tries;
    }

    /**
     * Seconds to wait between attempts of SendMailingJob.
     */
    public static function backoff(): int
    {
        return max(0, (int) config('newsletter.queue.backoff', 120));
    }

    /**
     * Set the configured connection and queue on a queueable object.
     *
     * @template T of object
     *
     * @param  T  $queueable
     * @return T
     */
    public static function applyTo(object $queueable): object
    {
        $connection = self::connection();
        if ($connection !== null && method_exists($queueable, 'onConnection')) {
            $queueable->onConnection($connection);
        }

        $name = self::name();
        if ($name !== null && method_exists($queueable, 'onQueue')) {
            $queueable->onQueue($name);
        }

        return $queueable;
    }
}
