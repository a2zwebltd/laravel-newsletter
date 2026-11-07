<?php

use A2ZWeb\Newsletter\Events\SubscriberVerified;
use A2ZWeb\Newsletter\Mail\NewsletterVerificationMail;
use A2ZWeb\Newsletter\Models\MailingSubscriber;
use A2ZWeb\Newsletter\Tests\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

it('creates a pending subscriber and sends a verification email', function () {
    Mail::fake();

    $this->postJson(route('newsletter.subscribe'), ['email' => 'New@Example.com'])
        ->assertOk();

    $subscriber = MailingSubscriber::where('email', 'new@example.com')->first();
    expect($subscriber)->not->toBeNull()
        ->and($subscriber->getVerifiedAt())->toBeNull()
        ->and($subscriber->getSource())->toBe('website');

    Mail::assertSent(NewsletterVerificationMail::class);
});

it('verifies a subscriber by token', function () {
    $subscriber = MailingSubscriber::factory()->unverified()->create();

    $this->get(route('newsletter.verify', ['token' => $subscriber->getToken()]))
        ->assertRedirect();

    expect($subscriber->fresh()->getVerifiedAt())->not->toBeNull();
});

it('flips the marketing flag at once for a signed-in user subscribing their own address', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'member@example.com', 'marketing' => false]);

    $this->actingAs($user)
        ->postJson(route('newsletter.subscribe'), ['email' => 'Member@Example.com'])
        ->assertOk();

    expect($user->fresh()->marketing)->toBeTrue()
        ->and(MailingSubscriber::where('email', 'member@example.com')->exists())->toBeFalse();
    Mail::assertNothingSent();
});

it('does not opt a registered user in when someone else posts their address', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'member@example.com', 'marketing' => false]);
    $stranger = User::factory()->create(['email' => 'stranger@example.com']);

    $this->postJson(route('newsletter.subscribe'), ['email' => 'member@example.com'])->assertOk();
    $this->actingAs($stranger)->postJson(route('newsletter.subscribe'), ['email' => 'member@example.com'])->assertOk();

    expect($user->fresh()->marketing)->toBeFalse();
    Mail::assertSent(NewsletterVerificationMail::class, fn ($mail) => $mail->hasTo('member@example.com'));
});

it('moves the opt-in onto the registered user once the inbox owner confirms', function () {
    Mail::fake();
    Event::fake([SubscriberVerified::class]);
    $user = User::factory()->create(['email' => 'member@example.com', 'marketing' => false]);

    $this->postJson(route('newsletter.subscribe'), ['email' => 'member@example.com'])->assertOk();
    $subscriber = MailingSubscriber::where('email', 'member@example.com')->firstOrFail();

    $this->get(route('newsletter.verify', ['token' => $subscriber->getToken()]))->assertRedirect();

    expect($user->fresh()->marketing)->toBeTrue()
        ->and(MailingSubscriber::where('email', 'member@example.com')->exists())->toBeFalse();
    Event::assertDispatched(SubscriberVerified::class);
});

it('treats a registered user like any other address when subscribe.registered_users is subscriber', function () {
    Mail::fake();
    Event::fake([SubscriberVerified::class]);
    config(['newsletter.subscribe.registered_users' => 'subscriber']);
    $user = User::factory()->create(['email' => 'member@example.com', 'marketing' => false]);

    $this->postJson(route('newsletter.subscribe'), ['email' => 'Member@Example.com'])
        ->assertOk();

    $subscriber = MailingSubscriber::where('email', 'member@example.com')->first();
    expect($subscriber)->not->toBeNull()
        ->and($subscriber->isVerified())->toBeFalse()
        ->and($user->fresh()->marketing)->toBeFalse();
    Mail::assertSent(NewsletterVerificationMail::class, fn ($mail) => $mail->hasTo('member@example.com'));

    $this->get(route('newsletter.verify', ['token' => $subscriber->getToken()]))->assertRedirect();

    expect($subscriber->fresh()->isVerified())->toBeTrue();
    Event::assertDispatched(SubscriberVerified::class);
});

it('keeps the callback mode for registered users by default and creates no subscriber row', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'member@example.com', 'marketing' => false]);

    $this->actingAs($user)
        ->postJson(route('newsletter.subscribe'), ['email' => 'member@example.com'])
        ->assertOk();

    expect(MailingSubscriber::where('email', 'member@example.com')->exists())->toBeFalse();
    Mail::assertNothingSent();
});

it('rejects an invalid email', function () {
    $this->postJson(route('newsletter.subscribe'), ['email' => 'not-an-email'])
        ->assertStatus(422);
});
