<?php

declare(strict_types=1);

use A2ZWeb\Newsletter\Http\Controllers\NewsletterController;
use A2ZWeb\Newsletter\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Public archive pages.
if (config('newsletter.routes.archive_enabled', true)) {
    Route::get('/newsletters', [NewsletterController::class, 'index'])->name('newsletters');
    Route::get('/newsletter_form', [NewsletterController::class, 'form'])->name('newsletters.form');
    Route::get('/newsletters/{slug}', [NewsletterController::class, 'item'])->name('newsletters.item');
    Route::get('/mailing/{slug}', [NewsletterController::class, 'preview'])->name('mailing');
}

// Subscription lifecycle.
Route::post('/newsletter/subscribe', [SubscriptionController::class, 'subscribe'])
    ->middleware(config('newsletter.routes.subscribe_middleware', []))
    ->name('newsletter.subscribe');

Route::get('/newsletter/verify/{token}', [SubscriptionController::class, 'verify'])
    ->name('newsletter.verify');

Route::get('/unsubscribe/{uuid}', [SubscriptionController::class, 'showUnsubscribe'])
    ->name('unsubscribe.show');

Route::post('/unsubscribe/{uuid}', [SubscriptionController::class, 'confirmUnsubscribe'])
    ->name('unsubscribe.confirm');
