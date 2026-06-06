<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mailings')) {
            return;
        }

        Schema::create('mailings', function (Blueprint $table): void {
            $table->id();
            $table->efficientUuid('uuid')->nullable()->unique();
            $table->foreignId('mailing_type_id')->constrained('mailing_types');
            $table->string('template')->default('mailing');
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->text('content_extra')->nullable();
            $table->text('extra_html')->nullable();
            $table->string('slug')->nullable();
            $table->string('cta_content')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('cta_url_short')->nullable();
            $table->string('reply_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailings');
    }
};
