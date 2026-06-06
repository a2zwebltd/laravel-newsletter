<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Console\Commands;

use A2ZWeb\Newsletter\Jobs\SendMailingJob;
use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use Illuminate\Console\Command;

class SendScheduledMailings extends Command
{
    protected $signature = 'mailings:send-scheduled';

    protected $description = 'Send mailings that have reached their scheduled time';

    public function handle(): int
    {
        $mailings = Mailing::query()
            ->whereNotNull('approved_at')
            ->whereNull('sent_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        $perMinute = max(1, (int) config('newsletter.per_minute', 12));

        foreach ($mailings as $mailing) {
            $mailing->sent_at = now();
            $mailing->saveQuietly();

            $recipients = MailingRecipient::where('mailing_id', $mailing->id)
                ->whereNull('sent_at')
                ->get();

            $delay = 0;
            foreach ($recipients as $recipient) {
                SendMailingJob::dispatch($mailing, $recipient)->delay($delay);
                $delay += 60 / $perMinute;
            }

            $this->info("Dispatched {$recipients->count()} jobs for mailing #{$mailing->id}: {$mailing->title}");
        }

        if ($mailings->isEmpty()) {
            $this->info('No scheduled mailings to send.');
        }

        return self::SUCCESS;
    }
}
