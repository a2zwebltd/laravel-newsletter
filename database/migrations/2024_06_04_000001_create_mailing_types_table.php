<?php

declare(strict_types=1);

use A2ZWeb\Newsletter\Models\MailingType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A host that already has a `mailing_types` table (its own, or an
        // earlier install) keeps it untouched: seeding into a table of another
        // shape is how a migration breaks on a column it never created.
        if (Schema::hasTable('mailing_types')) {
            return;
        }

        Schema::create('mailing_types', function (Blueprint $table): void {
            $table->id();
            $table->efficientUuid('uuid')->nullable()->unique();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Seed the default mailing types used to scope an audience.
        foreach ([
            'verified_users' => 'Verified Users',
            'verified_consent' => 'Verified Users with Marketing Consent',
        ] as $code => $name) {
            MailingType::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_types');
    }
};
