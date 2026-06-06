<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Database\Factories;

use A2ZWeb\Newsletter\Models\MailingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailingType>
 */
class MailingTypeFactory extends Factory
{
    protected $model = MailingType::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->word(),
            'name' => $this->faker->sentence(2),
        ];
    }
}
