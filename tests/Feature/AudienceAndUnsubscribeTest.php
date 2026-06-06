<?php

use A2ZWeb\Newsletter\Models\MailingSubscriber;
use A2ZWeb\Newsletter\Support\AudienceResolver;
use A2ZWeb\Newsletter\Tests\User;

it('resolves verified users and verified subscribers as the audience', function () {
    User::factory()->count(2)->create();              // verified
    User::factory()->unverified()->create();          // excluded
    MailingSubscriber::factory()->count(3)->create(); // verified
    MailingSubscriber::factory()->unverified()->create();

    $resolver = app(AudienceResolver::class);

    expect($resolver->usersQuery()->count())->toBe(2)
        ->and($resolver->subscribersQuery()->count())->toBe(3);
});

it('unsubscribes a registered user via the configured callback', function () {
    $user = User::factory()->create(['marketing' => true]);

    $this->post(route('unsubscribe.confirm', ['uuid' => $user->uuid]))
        ->assertRedirect();

    expect($user->fresh()->marketing)->toBeFalse();
});

it('deletes a subscriber on unsubscribe', function () {
    $subscriber = MailingSubscriber::factory()->create();

    $this->post(route('unsubscribe.confirm', ['uuid' => $subscriber->getToken()]))
        ->assertRedirect();

    expect(MailingSubscriber::find($subscriber->id))->toBeNull();
});
