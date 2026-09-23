<?php

use A2ZWeb\Newsletter\Mail\SendMailingEmail;
use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use A2ZWeb\Newsletter\Tests\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

it('still sends to a user without a uuid but logs that the unsubscribe link was left out', function () {
    Log::spy();
    config(['mail.from.address' => 'news@example.com']);
    $user = User::factory()->create(['uuid' => null]);
    $mailing = Mailing::factory()->approved()->create();
    $recipient = MailingRecipient::factory()->create(['mailing_id' => $mailing->id, 'user_id' => $user->id]);

    Mail::to($user->email)->send(new SendMailingEmail($mailing, $recipient));

    $message = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();

    expect($message->getHeaders()->has('List-Unsubscribe'))->toBeFalse()
        ->and($message->getHtmlBody())->not->toContain('/unsubscribe/');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $line) => str_contains($line, 'has no uuid') && str_contains($line, User::class))
        ->once();
});

it('does not warn for a test send without a recipient model', function () {
    Log::spy();
    $mailing = Mailing::factory()->approved()->create();

    Mail::to('someone@example.com')->send(new SendMailingEmail($mailing, new MailingRecipient));

    Log::shouldNotHaveReceived('warning');
});
