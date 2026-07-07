<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class MailingStatusFilter extends Filter
{
    public $name = 'Status';

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return match ($value) {
            'draft' => $query->whereNull('approved_at'),
            'approved' => $query->whereNotNull('approved_at')->whereNull('sent_at')->whereNull('scheduled_at'),
            'scheduled' => $query->whereNotNull('scheduled_at')->whereNull('sent_at'),
            'sent' => $query->whereNotNull('sent_at'),
            default => $query,
        };
    }

    /**
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        return [
            __('Draft') => 'draft',
            __('Approved') => 'approved',
            __('Scheduled') => 'scheduled',
            __('Sent') => 'sent',
        ];
    }
}
