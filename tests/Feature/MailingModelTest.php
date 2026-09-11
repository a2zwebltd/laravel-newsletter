<?php

use A2ZWeb\Newsletter\Events\MailingApproved;
use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use A2ZWeb\Newsletter\Models\MailingSubscriber;
use A2ZWeb\Newsletter\Models\MailingType;
use A2ZWeb\Newsletter\Tests\User;
use Illuminate\Support\Facades\Event;

it('boots the schema and seeds default mailing types', function () {
    expect(MailingType::where('code', 'verified_users')->exists())->toBeTrue()
        ->and(MailingType::where('code', 'verified_consent')->exists())->toBeTrue();
});

it('creates a mailing with a generated uuid and slug', function () {
    $mailing = Mailing::factory()->create(['title' => 'Hello World']);

    expect($mailing->getUuid())->not->toBeNull()
        ->and($mailing->getSlug())->not->toBeNull()
        ->and($mailing->mailingType)->toBeInstanceOf(MailingType::class);
});

it('generates a token for a new subscriber', function () {
    $subscriber = MailingSubscriber::create(['email' => 'sub@example.com']);

    expect($subscriber->getToken())->not->toBeEmpty()
        ->and($subscriber->getMailingEmail())->toBe('sub@example.com');
});

it('links a recipient to its subscriber and mailing', function () {
    $recipient = MailingRecipient::factory()->create();

    expect($recipient->mailing)->toBeInstanceOf(Mailing::class)
        ->and($recipient->subscriber)->toBeInstanceOf(MailingSubscriber::class);
});

it('approve() enrols the audience as recipients and fires MailingApproved', function () {
    Event::fake([MailingApproved::class]);
    User::factory()->count(2)->create();
    User::factory()->unverified()->create();
    MailingSubscriber::factory()->count(3)->create();
    MailingSubscriber::factory()->unsubscribed()->create();
    MailingSubscriber::factory()->unverified()->create();

    $mailing = Mailing::factory()->create();

    $mailing->approve();

    expect($mailing->isApproved())->toBeTrue()
        ->and($mailing->mailingRecipients()->count())->toBe(5)
        ->and($mailing->mailingRecipients()->whereNotNull('user_id')->count())->toBe(2)
        ->and($mailing->mailingRecipients()->whereNotNull('subscriber_id')->count())->toBe(3);
    Event::assertDispatched(MailingApproved::class, fn ($event) => $event->mailing->is($mailing));

    // Approving again is a no-op.
    $mailing->approve();
    Event::assertDispatchedTimes(MailingApproved::class, 1);
});
