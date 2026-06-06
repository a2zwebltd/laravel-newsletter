<?php

use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use A2ZWeb\Newsletter\Models\MailingSubscriber;
use A2ZWeb\Newsletter\Models\MailingType;

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
