<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class SubscriberVerifiedFilter extends Filter
{
    public $name = 'Verification';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return match ($value) {
            'verified' => $query->whereNotNull('verified_at'),
            'unverified' => $query->whereNull('verified_at'),
            default => $query,
        };
    }

    /**
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        return [
            __('Verified') => 'verified',
            __('Unverified') => 'unverified',
        ];
    }
}
