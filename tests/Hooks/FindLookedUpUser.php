<?php

namespace A2ZWeb\Newsletter\Tests\Hooks;

use A2ZWeb\Newsletter\Tests\User;

class FindLookedUpUser
{
    public function __invoke(string $uuid): ?User
    {
        return User::query()->where('email', 'looked-up@example.com')->first();
    }
}
