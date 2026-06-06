<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Metrics;

use A2ZWeb\Newsletter\Models\Mailing;
use Carbon\Carbon;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;

class MailingsSentPerMonth extends Trend
{
    public function calculate(NovaRequest $request): TrendResult
    {
        return $this->countByMonths($request, Mailing::query()->whereNotNull('approved_at'), 'approved_at')
            ->showSumValue();
    }

    public function name(): string
    {
        return __('Mailings Sent Per Month');
    }

    public function ranges(): array
    {
        $firstSent = Mailing::query()->whereNotNull('approved_at')->min('approved_at');
        $allTimeMonths = $firstSent
            ? (int) Carbon::parse($firstSent)->startOfMonth()->diffInMonths(now()) + 1
            : 12;

        return [
            12 => __('12 Months'),
            3 => __('3 Months'),
            6 => __('6 Months'),
            24 => __('24 Months'),
            $allTimeMonths => __('All Time'),
        ];
    }

    public function uriKey(): string
    {
        return 'mailings-sent-per-month';
    }
}
