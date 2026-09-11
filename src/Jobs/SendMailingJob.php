<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Jobs;

use A2ZWeb\Newsletter\Contracts\CanReceiveMailing;
use A2ZWeb\Newsletter\Events\MailingDelivered;
use A2ZWeb\Newsletter\Mail\SendMailingEmail;
use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use A2ZWeb\Newsletter\Support\NewsletterQueue;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendMailingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Attempts before the job is marked failed (`newsletter.queue.tries`).
     * Null keeps retrying for 24 hours, see retryUntil().
     */
    public ?int $tries;

    /**
     * Seconds between attempts (`newsletter.queue.backoff`).
     */
    public int $backoff;

    public function __construct(
        public readonly Mailing $mailing,
        public readonly MailingRecipient $recipient,
    ) {
        $this->tries = NewsletterQueue::tries();
        $this->backoff = NewsletterQueue::backoff();

        NewsletterQueue::applyTo($this);
    }

    public function uniqueId(): string
    {
        return (string) $this->recipient->getId();
    }

    public function middleware(): array
    {
        return [(new RateLimited('mailings'))->dontRelease()];
    }

    /**
     * Laravel gives retryUntil() precedence over $tries, so the 24-hour window
     * only applies when `newsletter.queue.tries` is null.
     */
    public function retryUntil(): ?DateTime
    {
        if ($this->tries !== null) {
            return null;
        }

        return now()->addMinutes(60 * 24);
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $recipientEmail = null;

        try {
            if (! $this->recipient->sent_at) {
                $recipientModel = $this->resolveRecipientModel();

                if (! $recipientModel instanceof CanReceiveMailing) {
                    $reason = 'Neither a user nor a subscriber was resolved for this recipient.';
                    $this->recipient->markAsFailed($reason);
                    $this->fail(__METHOD__.': '.$reason);

                    return;
                }

                $recipientEmail = $recipientModel->getMailingEmail();

                Mail::to($recipientEmail)->send(new SendMailingEmail($this->mailing, $this->recipient));

                event(new MailingDelivered($this->mailing, $this->recipient, $recipientModel));
            } else {
                Log::debug('Mailing recipient already sent at '.$this->recipient->sent_at->format('Y-m-d H:i:s'));
            }

            $this->recipient->sent_at = now();
            $this->recipient->failed_at = null;
            $this->recipient->saveQuietly();

            Log::debug('Newsletter email successfully sent to: '.$recipientEmail);
        } catch (Exception $e) {
            Log::error('Failed to send newsletter email to: '.$recipientEmail.'. Error: '.$e->getMessage());

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendMailingJob failed for recipient: '.$this->recipient->getId().'. Error: '.$exception->getMessage());

        $this->recipient->markAsFailed($exception->getMessage());
    }

    protected function resolveRecipientModel(): ?CanReceiveMailing
    {
        if ($this->recipient->user instanceof CanReceiveMailing) {
            return $this->recipient->user;
        }

        if ($this->recipient->subscriber instanceof CanReceiveMailing) {
            return $this->recipient->subscriber;
        }

        return null;
    }
}
