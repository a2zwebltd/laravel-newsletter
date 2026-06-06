<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Actions;

use A2ZWeb\Newsletter\Jobs\SendMailingJob;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

class SendMailing extends Action implements ShouldQueue
{
    use Queueable;

    public $name = 'Send Mailing';

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $perMinute = max(1, (int) config('newsletter.per_minute', 12));

        foreach ($models as $mailing) {
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
        }

        return Action::message('Mailing is being sent.');
    }

    public function authorizedToRun(Request $request, $model): bool
    {
        return $model->approved_at !== null && $model->sent_at === null;
    }

    public function enabled(bool $value): static
    {
        $this->showOnIndex = false;
        $this->showOnDetail = $value;
        $this->showInline = $value;

        return $this;
    }
}
