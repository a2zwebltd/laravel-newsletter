<?php

declare(strict_types=1);

use A2ZWeb\Newsletter\Http\Controllers\NewsletterController;
use A2ZWeb\Newsletter\Http\Controllers\SubscriptionController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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
    ->middleware(config('newsletter.routes.subscribe_middleware', ['throttle:10,1']))
    ->name('newsletter.subscribe');

Route::get('/newsletter/verify/{token}', [SubscriptionController::class, 'verify'])
    ->name('newsletter.verify');

Route::get('/unsubscribe/{uuid}', [SubscriptionController::class, 'showUnsubscribe'])
    ->name('unsubscribe.show');

Route::post('/unsubscribe/{uuid}', [SubscriptionController::class, 'confirmUnsubscribe'])
    ->name('unsubscribe.confirm');

// RFC 8058 one-click unsubscribe: mail clients POST here without a CSRF token.
// Excluding the framework base class also excludes the host's own subclass.
$oneClick = Route::match(['GET', 'POST'], '/unsubscribe/{uuid}/one-click', [SubscriptionController::class, 'oneClickUnsubscribe'])
    ->name('unsubscribe.one-click');

$withoutCsrf = array_values(array_filter(
    [VerifyCsrfToken::class, ValidateCsrfToken::class],
    fn (string $middleware): bool => class_exists($middleware),
));

if ($withoutCsrf !== []) {
    $oneClick->withoutMiddleware($withoutCsrf);
}
