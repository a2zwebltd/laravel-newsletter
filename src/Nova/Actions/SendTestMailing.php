<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Actions;

use A2ZWeb\Newsletter\Contracts\CanReceiveMailing;
use A2ZWeb\Newsletter\Mail\SendMailingEmail;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use A2ZWeb\Newsletter\Models\MailingSubscriber;
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

        // Preview personalization realistically: if the test address belongs to a real
        // audience member, attach it so {name}/unsubscribe render as they would in production.
        // Otherwise the mailable falls back to its generic greeting.
        $recipientModel = $this->resolveRecipientByEmail($testEmailAddress);

        foreach ($models as $mailing) {
            $recipient = new MailingRecipient;

            if ($recipientModel instanceof MailingSubscriber) {
                $recipient->setRelation('subscriber', $recipientModel);
            } elseif ($recipientModel instanceof CanReceiveMailing) {
                $recipient->setRelation('user', $recipientModel);
            }

            Mail::to($testEmailAddress)->send(new SendMailingEmail($mailing, $recipient));
        }

        return Action::message('Test mailing email sent successfully.');
    }

    /**
     * Resolve a real audience member matching the test address, so the preview
     * personalizes the same way a production send would. Returns null when the
     * address belongs to no registered user or subscriber.
     */
    protected function resolveRecipientByEmail(string $email): ?CanReceiveMailing
    {
        $userModel = config('newsletter.user_model');

        if (is_string($userModel) && class_exists($userModel)) {
            $user = $userModel::where('email', $email)->first();

            if ($user instanceof CanReceiveMailing) {
                return $user;
            }
        }

        return MailingSubscriber::where('email', $email)->first();
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
