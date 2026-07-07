<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Actions;

use A2ZWeb\Newsletter\Jobs\SendMailingJob;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

/**
 * Re-queues delivery for every recipient of a sent mailing that previously
 * failed. Clears their failed_at marker and re-dispatches SendMailingJob with
 * the same per-minute pacing as a normal send.
 */
class ResendFailedRecipients extends Action
{
    use Queueable;

    public $name = 'Resend to Failed Recipients';

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $perMinute = max(1, (int) config('newsletter.per_minute', 12));
        $total = 0;

        foreach ($models as $mailing) {
            $delay = 0;

            MailingRecipient::query()
                ->where('mailing_id', $mailing->id)
                ->whereNotNull('failed_at')
                ->whereNull('sent_at')
                ->each(function (MailingRecipient $recipient) use ($mailing, &$delay, &$total, $perMinute): void {
                    $recipient->failed_at = null;
                    $recipient->saveQuietly();

                    SendMailingJob::dispatch($mailing, $recipient)->delay($delay);
                    $delay += 60 / $perMinute;
                    $total++;
                });
        }

        return $total > 0
            ? Action::message(__(':count failed recipient(s) re-queued.', ['count' => $total]))
            : Action::danger(__('No failed recipients to resend.'));
    }

    public function authorizedToRun(Request $request, $model): bool
    {
        return $model->sent_at !== null;
    }

    public function enabled(bool $value): static
    {
        $this->showOnIndex = false;
        $this->showOnDetail = $value;
        $this->showInline = $value;

        return $this;
    }
}
