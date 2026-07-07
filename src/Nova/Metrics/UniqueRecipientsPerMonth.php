<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Metrics;

use A2ZWeb\Newsletter\Models\MailingRecipient;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendDateExpressionFactory;
use Laravel\Nova\Metrics\TrendResult;
use Laravel\Nova\Nova;

class UniqueRecipientsPerMonth extends Trend
{
    public function calculate(NovaRequest $request): TrendResult
    {
        $query = MailingRecipient::query()->whereNotNull('sent_at');

        $timezone = Nova::resolveUserTimezone($request) ?? config('app.timezone');

        $dateColumn = 'sent_at';

        $expression = (string) TrendDateExpressionFactory::make(
            $query, $dateColumn, self::BY_MONTHS, $timezone
        );

        $possibleDateResults = $this->getAllPossibleDateResults(
            $startingDate = $this->getAggregateStartingDate($request, self::BY_MONTHS, $timezone),
            $endingDate = CarbonImmutable::now($timezone),
            self::BY_MONTHS,
            $request->twelveHourTime === 'true',
            (int) $request->range
        );

        $results = $query
            ->select(DB::raw("{$expression} as date_result, COUNT(DISTINCT COALESCE(user_id, CONCAT('s', subscriber_id))) as aggregate"))
            ->tap(fn ($query) => $this->applyFilterQuery($request, $query))
            ->whereBetween($dateColumn, $this->formatQueryDateBetween([$startingDate, $endingDate]))
            ->groupBy(DB::raw($expression))
            ->orderBy('date_result')
            ->get();

        $possibleDateKeys = array_keys($possibleDateResults);

        $results = array_merge($possibleDateResults, $results->mapWithKeys(function ($result) use ($request) {
            return [$this->formatAggregateResultDate(
                $result->date_result, self::BY_MONTHS, $request->twelveHourTime === 'true'
            ) => (int) $result->aggregate];
        })->reject(fn ($value, $key) => ! in_array($key, $possibleDateKeys))->all());

        return $this->result(array_sum($results))->trend($results)->showSumValue();
    }

    public function name(): string
    {
        return __('Unique Recipients Per Month');
    }

    public function ranges(): array
    {
        $firstSent = MailingRecipient::query()->whereNotNull('sent_at')->min('sent_at');
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
        return 'unique-recipients-per-month';
    }
}
