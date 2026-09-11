<?php

use A2ZWeb\Newsletter\Events\MailingDelivered;
use A2ZWeb\Newsletter\Jobs\SendMailingJob;
use A2ZWeb\Newsletter\Mail\NewsletterVerificationMail;
use A2ZWeb\Newsletter\Mail\SendMailingEmail;
use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use A2ZWeb\Newsletter\Models\MailingSubscriber;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

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

it('is rate limited and retries three times two minutes apart by default', function () {
    $mailing = Mailing::factory()->approved()->create();
    $recipient = MailingRecipient::factory()->create(['mailing_id' => $mailing->id]);

    $job = new SendMailingJob($mailing, $recipient);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe(120)
        ->and($job->retryUntil())->toBeNull()
        ->and($job->connection)->toBeNull()
        ->and($job->queue)->toBeNull()
        ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class);
});

it('keeps the 24h retry window when queue.tries is null', function () {
    config(['newsletter.queue.tries' => null]);
    $mailing = Mailing::factory()->approved()->create();
    $recipient = MailingRecipient::factory()->create(['mailing_id' => $mailing->id]);

    $job = new SendMailingJob($mailing, $recipient);

    expect($job->tries)->toBeNull()
        ->and($job->retryUntil()->greaterThan(now()->addHours(23)))->toBeTrue();
});

it('honours the configured queue connection, name, tries and backoff', function () {
    config([
        'newsletter.queue.connection' => 'redis',
        'newsletter.queue.name' => 'newsletter',
        'newsletter.queue.tries' => '5',
        'newsletter.queue.backoff' => 30,
    ]);
    Queue::fake();

    $mailing = Mailing::factory()->approved()->create();
    $recipient = MailingRecipient::factory()->create(['mailing_id' => $mailing->id]);

    SendMailingJob::dispatch($mailing, $recipient);

    Queue::assertPushedOn('newsletter', SendMailingJob::class, function (SendMailingJob $job): bool {
        return $job->connection === 'redis' && $job->tries === 5 && $job->backoff === 30;
    });
});

it('puts the verification and mailing mailables on the configured queue', function () {
    config(['newsletter.queue.connection' => 'redis', 'newsletter.queue.name' => 'newsletter']);

    $verification = new NewsletterVerificationMail('token');
    $mailing = new SendMailingEmail(Mailing::factory()->approved()->create());

    expect($verification->connection)->toBe('redis')
        ->and($verification->queue)->toBe('newsletter')
        ->and($mailing->connection)->toBe('redis')
        ->and($mailing->queue)->toBe('newsletter');
});

it('stores a truncated failure reason when the job fails', function () {
    $mailing = Mailing::factory()->approved()->create();
    $recipient = MailingRecipient::factory()->create(['mailing_id' => $mailing->id]);

    (new SendMailingJob($mailing, $recipient))->failed(new RuntimeException(str_repeat('x', 600)));

    $recipient->refresh();
    expect($recipient->getFailedAt())->not->toBeNull()
        ->and(mb_strlen((string) $recipient->getFailureReason()))->toBe(500);
});

it('stores a failure reason when no address resolves for the recipient', function () {
    Mail::fake();
    $mailing = Mailing::factory()->approved()->create();
    $recipient = MailingRecipient::factory()->create([
        'mailing_id' => $mailing->id,
        'subscriber_id' => null,
        'user_id' => null,
    ]);

    (new SendMailingJob($mailing, $recipient))->handle();

    $recipient->refresh();
    Mail::assertNothingSent();
    expect($recipient->getSentAt())->toBeNull()
        ->and($recipient->getFailedAt())->not->toBeNull()
        ->and($recipient->getFailureReason())->toContain('Neither a user nor a subscriber');
});
