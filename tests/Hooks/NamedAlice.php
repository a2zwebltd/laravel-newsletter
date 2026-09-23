<?php

namespace A2ZWeb\Newsletter\Tests\Hooks;

use Illuminate\Database\Eloquent\Builder;

class NamedAlice
{
    public function __invoke(Builder $query): Builder
    {
        return $query->where('name', 'Alice');
    }
}
