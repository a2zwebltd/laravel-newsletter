<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Database\Factories;

use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mailing>
 */
class MailingFactory extends Factory
{
    protected $model = Mailing::class;

    public function definition(): array
    {
        return [
            'mailing_type_id' => MailingType::factory(),
            'template' => 'mailing',
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraph(),
            'slug' => $this->faker->unique()->slug(),
            'approved_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(['approved_at' => now()]);
    }
}
