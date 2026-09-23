<?php

use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingType;
use A2ZWeb\Newsletter\Support\AudienceResolver;
use A2ZWeb\Newsletter\Tests\Hooks\FindLookedUpUser;
use A2ZWeb\Newsletter\Tests\Hooks\MarketingUsers;
use A2ZWeb\Newsletter\Tests\Hooks\NamedAlice;
use A2ZWeb\Newsletter\Tests\User;
use Illuminate\Support\Str;

it('resolves the audience hooks from invokable class-strings', function () {
    config([
        'newsletter.audience.users_query' => MarketingUsers::class,
        'newsletter.audience.type_scopes' => ['alice_only' => NamedAlice::class],
    ]);

    User::factory()->create(['marketing' => true, 'name' => 'Alice']);
    User::factory()->create(['marketing' => true, 'name' => 'Bob']);
    User::factory()->create(['marketing' => false, 'name' => 'Alice']);

    $resolver = app(AudienceResolver::class);

    expect($resolver->usersQuery()->count())->toBe(2)
        ->and($resolver->usersQuery('alice_only')->count())->toBe(1);
});

it('builds recipients through a class-string users_query on approval', function () {
    config(['newsletter.audience.users_query' => MarketingUsers::class]);

    User::factory()->create(['marketing' => true]);
    User::factory()->create(['marketing' => false]);
    $mailing = Mailing::factory()->create(['mailing_type_id' => MailingType::factory()->create()->id]);

    $mailing->approve();

    expect($mailing->mailingRecipients()->whereNotNull('user_id')->count())->toBe(1);
});

it('resolves find_user_by_uuid from an invokable class-string', function () {
    config(['newsletter.callbacks.find_user_by_uuid' => FindLookedUpUser::class]);
    $user = User::factory()->create(['email' => 'looked-up@example.com', 'marketing' => true]);

    $this->post(route('unsubscribe.confirm', ['uuid' => (string) Str::uuid()]))->assertRedirect();

    expect($user->fresh()->marketing)->toBeFalse();
});
