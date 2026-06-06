<?php

use A2ZWeb\Newsletter\Mail\NewsletterVerificationMail;
use A2ZWeb\Newsletter\Models\MailingSubscriber;
use A2ZWeb\Newsletter\Tests\User;
use Illuminate\Support\Facades\Mail;

it('creates a pending subscriber and sends a verification email', function () {
    Mail::fake();

    $this->postJson(route('newsletter.subscribe'), ['email' => 'New@Example.com'])
        ->assertCreated();

    $subscriber = MailingSubscriber::where('email', 'new@example.com')->first();
    expect($subscriber)->not->toBeNull()
        ->and($subscriber->getVerifiedAt())->toBeNull();

    Mail::assertSent(NewsletterVerificationMail::class);
});

it('verifies a subscriber by token', function () {
    $subscriber = MailingSubscriber::factory()->unverified()->create();

    $this->get(route('newsletter.verify', ['token' => $subscriber->getToken()]))
        ->assertRedirect();

    expect($subscriber->fresh()->getVerifiedAt())->not->toBeNull();
});

it('flips the marketing flag for an existing registered user', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'member@example.com', 'marketing' => false]);

    $this->postJson(route('newsletter.subscribe'), ['email' => 'member@example.com'])
        ->assertOk();

    expect($user->fresh()->marketing)->toBeTrue();
    Mail::assertNothingSent();
});

it('rejects an invalid email', function () {
    $this->postJson(route('newsletter.subscribe'), ['email' => 'not-an-email'])
        ->assertStatus(422);
});
