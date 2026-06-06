<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Database\Factories;

use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use A2ZWeb\Newsletter\Models\MailingSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailingRecipient>
 */
class MailingRecipientFactory extends Factory
{
    protected $model = MailingRecipient::class;

    public function definition(): array
    {
        return [
            'mailing_id' => Mailing::factory(),
            'subscriber_id' => MailingSubscriber::factory(),
            'user_id' => null,
            'sent_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(['sent_at' => now()]);
    }
}
