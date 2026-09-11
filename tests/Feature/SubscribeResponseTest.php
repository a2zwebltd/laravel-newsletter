<?php

use A2ZWeb\Newsletter\Events\SubscriberVerified;
use A2ZWeb\Newsletter\Mail\NewsletterVerificationMail;
use A2ZWeb\Newsletter\Models\MailingSubscriber;
use A2ZWeb\Newsletter\Tests\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

const SUBSCRIBED_MESSAGE = 'Almost there. Open the e-mail we just sent and confirm the address, so we know it is you.';

it('answers 200 with the same message for a new, verified, unverified, unsubscribed or registered address', function () {
    Mail::fake();
    User::factory()->create(['email' => 'member@example.com']);
    MailingSubscriber::factory()->create(['email' => 'verified@example.com']);
    MailingSubscriber::factory()->unverified()->create(['email' => 'pending@example.com']);
    MailingSubscriber::factory()->unsubscribed()->create(['email' => 'gone@example.com']);

    foreach (['new@example.com', 'verified@example.com', 'pending@example.com', 'gone@example.com', 'member@example.com'] as $email) {
        $this->postJson(route('newsletter.subscribe'), ['email' => $email])
            ->assertOk()
            ->assertExactJson(['message' => SUBSCRIBED_MESSAGE]);
    }

    // New and pending addresses get a verification mail; verified, unsubscribed and registered do not.
    Mail::assertSentCount(2);
    Mail::assertSent(NewsletterVerificationMail::class, fn ($mail) => $mail->hasTo('new@example.com'));
    Mail::assertSent(NewsletterVerificationMail::class, fn ($mail) => $mail->hasTo('pending@example.com'));
});

it('uses the message configured in responses.subscribed', function () {
    Mail::fake();
    config(['newsletter.responses.subscribed' => 'Check your inbox.']);

    $this->postJson(route('newsletter.subscribe'), ['email' => 'new@example.com'])
        ->assertOk()
        ->assertExactJson(['message' => 'Check your inbox.']);
});

it('clears unsubscribed_at when the address subscribes again', function () {
    Mail::fake();
    $verified = MailingSubscriber::factory()->unsubscribed()->create(['email' => 'back@example.com']);
    $pending = MailingSubscriber::factory()->unverified()->unsubscribed()->create(['email' => 'pending@example.com']);

    $this->postJson(route('newsletter.subscribe'), ['email' => 'back@example.com'])->assertOk();
    $this->postJson(route('newsletter.subscribe'), ['email' => 'pending@example.com'])->assertOk();

    expect($verified->fresh()->isUnsubscribed())->toBeFalse()
        ->and($pending->fresh()->isUnsubscribed())->toBeFalse();
    Mail::assertSentCount(1);
    Mail::assertSent(NewsletterVerificationMail::class, fn ($mail) => $mail->hasTo('pending@example.com'));
});

it('stores the optional source and caps it at 64 characters', function () {
    Mail::fake();

    $this->postJson(route('newsletter.subscribe'), ['email' => 'footer@example.com', 'source' => 'footer'])->assertOk();
    $this->postJson(route('newsletter.subscribe'), ['email' => 'long@example.com', 'source' => str_repeat('s', 65)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('source');

    expect(MailingSubscriber::where('email', 'footer@example.com')->value('source'))->toBe('footer');
});

it('validates the address with the configured rule', function () {
    Mail::fake();

    $this->postJson(route('newsletter.subscribe'), ['email' => str_repeat('a', 250).'@example.com'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('throttles the subscribe endpoint to 10 requests per minute by default', function () {
    Mail::fake();

    foreach (range(1, 10) as $i) {
        $this->postJson(route('newsletter.subscribe'), ['email' => "burst{$i}@example.com"])->assertOk();
    }

    $this->postJson(route('newsletter.subscribe'), ['email' => 'burst11@example.com'])
        ->assertStatus(429);
});

it('fires SubscriberVerified only on the first verification', function () {
    Event::fake([SubscriberVerified::class]);
    $subscriber = MailingSubscriber::factory()->unverified()->create();

    $this->get(route('newsletter.verify', ['token' => $subscriber->getToken()]))->assertRedirect(route('home'));
    $this->get(route('newsletter.verify', ['token' => $subscriber->getToken()]))->assertRedirect(route('home'));

    expect($subscriber->fresh()->isVerified())->toBeTrue();
    Event::assertDispatchedTimes(SubscriberVerified::class, 1);
    Event::assertDispatched(SubscriberVerified::class, fn ($event) => $event->subscriber->is($subscriber));
});

it('renders the host view configured in views.verified instead of redirecting', function () {
    config(['newsletter.views.verified' => 'host::verified']);
    $subscriber = MailingSubscriber::factory()->unverified()->create(['email' => 'seen@example.com']);

    $this->get(route('newsletter.verify', ['token' => $subscriber->getToken()]))
        ->assertOk()
        ->assertSee('Host verified view for seen@example.com');

    expect($subscriber->fresh()->isVerified())->toBeTrue();
});
