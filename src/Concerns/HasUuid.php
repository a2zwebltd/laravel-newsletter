<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Concerns;

use Dyrynda\Database\Support\Casts\EfficientUuid;
use Dyrynda\Database\Support\GeneratesUuid;

/**
 * Generates a binary-efficient UUID for the model's `uuid` column.
 *
 * Mirrors the host application's UUID convention so existing data remains
 * readable. Fresh installs should create the column with the
 * `$table->efficientUuid('uuid')` blueprint macro.
 */
trait HasUuid
{
    use GeneratesUuid;

    protected $uuidVersion = 'uuid4';

    public function initializeHasUuid(): void
    {
        $this->casts['uuid'] = EfficientUuid::class;
    }
}
