<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class SubscriberUnsubscribedFilter extends Filter
{
    public $name = 'Subscription';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return match ($value) {
            'subscribed' => $query->whereNull('unsubscribed_at'),
            'unsubscribed' => $query->whereNotNull('unsubscribed_at'),
            default => $query,
        };
    }

    /**
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        return [
            __('Subscribed') => 'subscribed',
            __('Unsubscribed') => 'unsubscribed',
        ];
    }
}
