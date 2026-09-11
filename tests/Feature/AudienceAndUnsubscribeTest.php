<?php

use A2ZWeb\Newsletter\Events\SubscriberUnsubscribed;
use A2ZWeb\Newsletter\Models\MailingSubscriber;
use A2ZWeb\Newsletter\Support\AudienceResolver;
use A2ZWeb\Newsletter\Tests\User;
use Illuminate\Support\Facades\Event;

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

it('marks a subscriber unsubscribed and keeps the row by default', function () {
    Event::fake([SubscriberUnsubscribed::class]);
    $subscriber = MailingSubscriber::factory()->create();

    $this->post(route('unsubscribe.confirm', ['uuid' => $subscriber->getToken()]))
        ->assertRedirect(route('unsubscribe.show', ['uuid' => $subscriber->getToken(), 'unsubscribed' => true]));

    expect($subscriber->fresh()->isUnsubscribed())->toBeTrue();
    Event::assertDispatched(SubscriberUnsubscribed::class, fn ($event) => $event->subscriber->is($subscriber));
});

it('deletes a subscriber on unsubscribe in delete mode', function () {
    config(['newsletter.unsubscribe.mode' => 'delete']);
    Event::fake([SubscriberUnsubscribed::class]);
    $subscriber = MailingSubscriber::factory()->create();

    $this->post(route('unsubscribe.confirm', ['uuid' => $subscriber->getToken()]))
        ->assertRedirect();

    expect(MailingSubscriber::find($subscriber->id))->toBeNull();
    Event::assertDispatched(SubscriberUnsubscribed::class);
});

it('excludes unsubscribed subscribers from the audience', function () {
    MailingSubscriber::factory()->count(2)->create();
    MailingSubscriber::factory()->unsubscribed()->create();
    MailingSubscriber::factory()->unverified()->unsubscribed()->create();

    expect(app(AudienceResolver::class)->subscribersQuery()->count())->toBe(2)
        ->and(MailingSubscriber::subscribed()->count())->toBe(2);
});

it('shows the unsubscribe page as done for an already unsubscribed subscriber', function () {
    $subscriber = MailingSubscriber::factory()->unsubscribed()->create();

    $this->get(route('unsubscribe.show', ['uuid' => $subscriber->getToken()]))
        ->assertOk()
        ->assertSee('You have been unsubscribed');
});

it('renders the host view configured in views.unsubscribed', function () {
    config(['newsletter.views.unsubscribed' => 'host::unsubscribed']);
    $subscriber = MailingSubscriber::factory()->create();

    $this->get(route('unsubscribe.show', ['uuid' => $subscriber->getToken()]))
        ->assertOk()
        ->assertSee('Host unsubscribe view '.$subscriber->getToken().' confirm');
});
