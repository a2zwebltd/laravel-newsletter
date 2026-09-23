<?php

use A2ZWeb\Newsletter\Events\SubscriberUnsubscribed;
use A2ZWeb\Newsletter\Mail\SendMailingEmail;
use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use A2ZWeb\Newsletter\Models\MailingSubscriber;
use A2ZWeb\Newsletter\Tests\StrictCsrfToken;
use A2ZWeb\Newsletter\Tests\StrictRequestForgery;
use A2ZWeb\Newsletter\Tests\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

it('adds RFC 8058 List-Unsubscribe headers pointing at the one-click route', function () {
    config(['mail.from.address' => 'news@example.com']);
    $mailing = Mailing::factory()->approved()->create();
    $subscriber = MailingSubscriber::factory()->create();
    $recipient = MailingRecipient::factory()->create([
        'mailing_id' => $mailing->id,
        'subscriber_id' => $subscriber->id,
    ]);

    Mail::to($subscriber->getEmail())->send(new SendMailingEmail($mailing, $recipient));

    $headers = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getHeaders();
    $oneClickUrl = route('unsubscribe.one-click', ['uuid' => $subscriber->getToken()]);

    expect($headers->get('List-Unsubscribe')->getBodyAsString())
        ->toBe('<'.$oneClickUrl.'>, <mailto:news@example.com?subject=unsubscribe>')
        ->and($headers->get('List-Unsubscribe-Post')->getBodyAsString())
        ->toBe('List-Unsubscribe=One-Click');
});

it('unsubscribes on a one-click POST without a CSRF token while the confirm form keeps CSRF', function () {
    // The kernel's `web` group already carries ValidateCsrfToken, which skips itself in tests;
    // the strict subclass keeps the check on for this test.
    app(Kernel::class)->appendMiddlewareToGroup('web', StrictCsrfToken::class);
    Event::fake([SubscriberUnsubscribed::class]);
    $subscriber = MailingSubscriber::factory()->create();

    // Browser form without a token: rejected.
    $this->post(route('unsubscribe.confirm', ['uuid' => $subscriber->getToken()]))
        ->assertStatus(419);
    expect($subscriber->fresh()->isUnsubscribed())->toBeFalse();

    // Mail client one-click POST: form-encoded body, no token.
    $this->call(
        'POST',
        route('unsubscribe.one-click', ['uuid' => $subscriber->getToken()]),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
        'List-Unsubscribe=One-Click',
    )->assertNoContent();

    expect($subscriber->fresh()->isUnsubscribed())->toBeTrue();
    Event::assertDispatched(SubscriberUnsubscribed::class);
});

it('skips the CSRF middleware the framework puts in the web group on a one-click POST', function () {
    // Laravel 13's web group runs PreventRequestForgery, Laravel 11/12 run ValidateCsrfToken.
    // Resolve that exact entry to a strict subclass so the check really runs; the group itself
    // (and the name the route has to exclude) stays the framework default.
    $frameworkCsrf = class_exists(PreventRequestForgery::class) ? PreventRequestForgery::class : ValidateCsrfToken::class;
    expect(app(Kernel::class)->getMiddlewareGroups()['web'])->toContain($frameworkCsrf);
    app()->bind($frameworkCsrf, $frameworkCsrf === PreventRequestForgery::class ? StrictRequestForgery::class : StrictCsrfToken::class);

    $subscriber = MailingSubscriber::factory()->create();

    $this->post(route('unsubscribe.confirm', ['uuid' => $subscriber->getToken()]))
        ->assertStatus(419);

    $this->call(
        'POST',
        route('unsubscribe.one-click', ['uuid' => $subscriber->getToken()]),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
        'List-Unsubscribe=One-Click',
    )->assertNoContent();

    expect($subscriber->fresh()->isUnsubscribed())->toBeTrue();
});

it('skips a host subclass of PreventRequestForgery on a one-click POST', function () {
    app(Kernel::class)->appendMiddlewareToGroup('web', StrictRequestForgery::class);
    $subscriber = MailingSubscriber::factory()->create();

    $this->post(route('unsubscribe.confirm', ['uuid' => $subscriber->getToken()]))
        ->assertStatus(419);

    $this->post(route('unsubscribe.one-click', ['uuid' => $subscriber->getToken()]), ['List-Unsubscribe' => 'One-Click'])
        ->assertNoContent();

    expect($subscriber->fresh()->isUnsubscribed())->toBeTrue();
})->skip(! class_exists(PreventRequestForgery::class), 'PreventRequestForgery exists from Laravel 13');

it('ignores a one-click POST without the One-Click body', function () {
    $subscriber = MailingSubscriber::factory()->create();

    $this->post(route('unsubscribe.one-click', ['uuid' => $subscriber->getToken()]), ['foo' => 'bar'])
        ->assertStatus(400);

    expect($subscriber->fresh()->isUnsubscribed())->toBeFalse();
});

it('sends a GET on the one-click link to the confirmation page', function () {
    $subscriber = MailingSubscriber::factory()->create();

    $this->get(route('unsubscribe.one-click', ['uuid' => $subscriber->getToken()]))
        ->assertRedirect(route('unsubscribe.show', ['uuid' => $subscriber->getToken()]));

    expect($subscriber->fresh()->isUnsubscribed())->toBeFalse();
});

it('runs the host unsubscribe callback for a registered user on one-click', function () {
    $user = User::factory()->create(['marketing' => true]);

    $this->post(route('unsubscribe.one-click', ['uuid' => $user->uuid]), ['List-Unsubscribe' => 'One-Click'])
        ->assertNoContent();

    expect($user->fresh()->marketing)->toBeFalse();
});
