<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mailing_recipients')) {
            return;
        }

        Schema::create('mailing_recipients', function (Blueprint $table): void {
            $table->id();
            $table->efficientUuid('uuid')->nullable()->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->foreignId('mailing_id')->constrained('mailings')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->foreignId('subscriber_id')->nullable()->constrained('mailing_subscribers')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['mailing_id', 'user_id'], 'mailing_recipients_mailing_user_unique');
            $table->unique(['mailing_id', 'subscriber_id'], 'mailing_recipients_mailing_subscriber_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_recipients');
    }
};
