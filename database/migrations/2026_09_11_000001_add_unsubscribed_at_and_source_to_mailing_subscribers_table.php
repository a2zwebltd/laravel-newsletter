<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_subscribers', function (Blueprint $table): void {
            if (! Schema::hasColumn('mailing_subscribers', 'unsubscribed_at')) {
                $table->timestamp('unsubscribed_at')->nullable()->after('verified_at');
            }

            if (! Schema::hasColumn('mailing_subscribers', 'source')) {
                $table->string('source', 64)->nullable()->after('unsubscribed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mailing_subscribers', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['unsubscribed_at', 'source'],
                fn (string $column): bool => Schema::hasColumn('mailing_subscribers', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
