<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Support;

use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use Illuminate\Support\Collection;

/**
 * Enrols the current audience (eligible users + subscribed, verified
 * subscribers) as recipients of a mailing. Idempotent: existing recipient rows
 * are kept, so it can run again to pick up new audience members.
 */
class MailingRecipientBuilder
{
    public function __construct(private readonly AudienceResolver $resolver) {}

    /**
     * @return int the number of recipient rows created
     */
    public function build(Mailing $mailing): int
    {
        $created = 0;
        $typeCode = $mailing->mailingType?->code;

        $this->resolver->usersQuery($typeCode)
            ->select('id')
            ->chunkById(500, function (Collection $users) use ($mailing, &$created): void {
                foreach ($users as $user) {
                    $recipient = MailingRecipient::firstOrCreate([
                        'mailing_id' => $mailing->id,
                        'user_id' => $user->id,
                    ]);

                    if ($recipient->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        $this->resolver->subscribersQuery()
            ->select('id')
            ->chunkById(500, function (Collection $subscribers) use ($mailing, &$created): void {
                foreach ($subscribers as $subscriber) {
                    $recipient = MailingRecipient::firstOrCreate([
                        'mailing_id' => $mailing->id,
                        'subscriber_id' => $subscriber->id,
                    ]);

                    if ($recipient->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        return $created;
    }
}
