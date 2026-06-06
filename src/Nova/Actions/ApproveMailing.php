<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Actions;

use A2ZWeb\Newsletter\Events\MailingApproved;
use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use A2ZWeb\Newsletter\Support\AudienceResolver;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class ApproveMailing extends Action
{
    use Queueable;

    public $name = 'Approve Mailing';

    public function handle(ActionFields $fields, Collection $models): ActionResponse|Action
    {
        foreach ($models as $mailing) {
            if ($mailing->approved_at === null) {
                $mailing->approved_at = Carbon::now();
                $mailing->save();

                $this->createRecipientsForMailing($mailing);

                event(new MailingApproved($mailing));
            }
        }

        return Action::message('Mailing approved successfully and recipients have been created.');
    }

    public function fields(NovaRequest $request): array
    {
        return [];
    }

    protected function createRecipientsForMailing(Mailing $mailing): void
    {
        $resolver = app(AudienceResolver::class);
        $typeCode = $mailing->mailingType?->code;

        $resolver->usersQuery($typeCode)
            ->select('id')
            ->chunkById(500, function (Collection $users) use ($mailing): void {
                foreach ($users as $user) {
                    MailingRecipient::firstOrCreate([
                        'mailing_id' => $mailing->id,
                        'user_id' => $user->id,
                    ]);
                }
            });

        $resolver->subscribersQuery()
            ->select('id')
            ->chunkById(500, function (Collection $subscribers) use ($mailing): void {
                foreach ($subscribers as $subscriber) {
                    MailingRecipient::firstOrCreate([
                        'mailing_id' => $mailing->id,
                        'subscriber_id' => $subscriber->id,
                    ]);
                }
            });
    }

    public function authorizedToRun($request, $model): bool
    {
        return $model->approved_at === null;
    }

    public function enabled(bool $value): static
    {
        $this->showOnIndex = false;
        $this->showOnDetail = $value;
        $this->showInline = $value;

        return $this;
    }
}
