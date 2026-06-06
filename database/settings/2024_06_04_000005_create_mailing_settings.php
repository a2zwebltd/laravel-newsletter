<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->addIfMissing('mailing.test_email_address', null);
        $this->addIfMissing('mailing.from_email_address', null);
        $this->addIfMissing('mailing.from_name', null);
        $this->addIfMissing('mailing.extra_delay_seconds', null);
    }

    /**
     * Add a settings property unless it already exists — keeps the migration
     * idempotent when the `mailing` group was created by a host application.
     */
    private function addIfMissing(string $property, mixed $value): void
    {
        [$group, $name] = explode('.', $property, 2);

        if (Schema::hasTable('settings')
            && DB::table('settings')->where('group', $group)->where('name', $name)->exists()) {
            return;
        }

        $this->migrator->add($property, $value);
    }
};
