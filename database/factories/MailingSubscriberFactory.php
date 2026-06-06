<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Database\Factories;

use A2ZWeb\Newsletter\Models\MailingSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MailingSubscriber>
 */
class MailingSubscriberFactory extends Factory
{
    protected $model = MailingSubscriber::class;

    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'token' => (string) Str::uuid(),
            'verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(['verified_at' => null]);
    }
}
