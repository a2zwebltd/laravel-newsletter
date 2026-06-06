<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Metrics;

use A2ZWeb\Newsletter\Models\Mailing;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

class TotalMailingsSent extends Value
{
    public function calculate(NovaRequest $request): ValueResult
    {
        $total = Mailing::query()->whereNotNull('approved_at')->count();

        return (new ValueResult($total))->format('0,0');
    }

    public function name(): string
    {
        return __('Total Mailings Sent');
    }

    public function ranges(): array
    {
        return [];
    }

    public function uriKey(): string
    {
        return 'total-mailings-sent';
    }
}
