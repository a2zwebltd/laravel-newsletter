<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Actions;

use A2ZWeb\Newsletter\Mail\SendMailingEmail;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use A2ZWeb\Newsletter\Settings\MailingSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class SendTestMailing extends Action implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public $name = 'Send Test Mailing';

    public function handle(ActionFields $fields, Collection $models): Action|ActionResponse
    {
        $testEmailAddress = $fields->get('test_email_address') ?? app(MailingSettings::class)->test_email_address;

        if (empty($testEmailAddress)) {
            return Action::danger('Test email address is not set.');
        }

        foreach ($models as $mailing) {
            $recipient = new MailingRecipient;
            Mail::to($testEmailAddress)->send(new SendMailingEmail($mailing, $recipient));
        }

        return Action::message('Test mailing email sent successfully.');
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Test Email Address', 'test_email_address')
                ->default(app(MailingSettings::class)->test_email_address)
                ->rules('required', 'email')
                ->help('Enter the email address where the test mailing will be sent.'),
        ];
    }

    public function enabled(bool $value): static
    {
        $this->showOnIndex = false;
        $this->showOnDetail = $value;
        $this->showInline = $value;

        return $this;
    }
}
