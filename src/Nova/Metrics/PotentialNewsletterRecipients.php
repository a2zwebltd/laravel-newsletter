<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Metrics;

use A2ZWeb\Newsletter\Support\AudienceResolver;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

class PotentialNewsletterRecipients extends Value
{
    public function calculate(NovaRequest $request): ValueResult
    {
        $resolver = app(AudienceResolver::class);

        $users = $resolver->usersQuery()->count();
        $subscribers = $resolver->subscribersQuery()->count();

        return (new ValueResult($users + $subscribers))->format('0,0');
    }

    public function name(): string
    {
        return __('Potential Newsletter Recipients');
    }

    public function ranges(): array
    {
        return [];
    }

    public function uriKey(): string
    {
        return 'potential-newsletter-recipients';
    }
}
