<?php

use A2ZWeb\Newsletter\Events\MailingDelivered;
use A2ZWeb\Newsletter\Jobs\SendMailingJob;
use A2ZWeb\Newsletter\Mail\SendMailingEmail;
use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use A2ZWeb\Newsletter\Models\MailingSubscriber;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

it('sends the mailing, fires MailingDelivered and marks the recipient sent', function () {
    Mail::fake();
    Event::fake([MailingDelivered::class]);

    $mailing = Mailing::factory()->approved()->create();
    $subscriber = MailingSubscriber::factory()->create();
    $recipient = MailingRecipient::factory()->create([
        'mailing_id' => $mailing->id,
        'subscriber_id' => $subscriber->id,
        'user_id' => null,
        'sent_at' => null,
    ]);

    (new SendMailingJob($mailing, $recipient))->handle();

    Mail::assertSent(SendMailingEmail::class);
    Event::assertDispatched(MailingDelivered::class);
    expect($recipient->fresh()->getSentAt())->not->toBeNull();
});

it('does not resend an already-sent recipient', function () {
    Mail::fake();

    $mailing = Mailing::factory()->approved()->create();
    $recipient = MailingRecipient::factory()->sent()->create(['mailing_id' => $mailing->id]);

    (new SendMailingJob($mailing, $recipient))->handle();

    Mail::assertNothingSent();
});

it('auto-retries a failed send for up to ~24h and is rate limited', function () {
    $mailing = Mailing::factory()->approved()->create();
    $recipient = MailingRecipient::factory()->create(['mailing_id' => $mailing->id]);

    $job = new SendMailingJob($mailing, $recipient);

    expect($job->retryUntil()->greaterThan(now()->addHours(23)))->toBeTrue()
        ->and($job->middleware()[0])->toBeInstanceOf(\Illuminate\Queue\Middleware\RateLimited::class);
});
