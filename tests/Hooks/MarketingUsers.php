<?php

namespace A2ZWeb\Newsletter\Tests\Hooks;

use Illuminate\Database\Eloquent\Builder;

class MarketingUsers
{
    public function __invoke(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified_at')->where('marketing', true);
    }
}
