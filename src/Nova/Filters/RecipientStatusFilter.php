<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class RecipientStatusFilter extends Filter
{
    public $name = 'Status';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return match ($value) {
            'pending' => $query->whereNull('sent_at')->whereNull('failed_at'),
            'sent' => $query->whereNotNull('sent_at'),
            'failed' => $query->whereNotNull('failed_at'),
            default => $query,
        };
    }

    /**
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        return [
            __('Pending') => 'pending',
            __('Sent') => 'sent',
            __('Failed') => 'failed',
        ];
    }
}
