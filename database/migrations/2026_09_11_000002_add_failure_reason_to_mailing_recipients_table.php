<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('mailing_recipients', 'failure_reason')) {
            return;
        }

        Schema::table('mailing_recipients', function (Blueprint $table): void {
            $table->text('failure_reason')->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('mailing_recipients', 'failure_reason')) {
            return;
        }

        Schema::table('mailing_recipients', function (Blueprint $table): void {
            $table->dropColumn('failure_reason');
        });
    }
};
