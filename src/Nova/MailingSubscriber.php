<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova;

use A2ZWeb\Newsletter\Models\MailingSubscriber as MailingSubscriberModel;
use A2ZWeb\Newsletter\Nova\Filters\SubscriberVerifiedFilter;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Email;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

class MailingSubscriber extends Resource
{
    public static string $model = MailingSubscriberModel::class;

    public static $title = 'email';

    public static $perPageViaRelationship = 10;

    public static $search = ['id', 'email', 'token'];

    public static function group(): string
    {
        return (string) (config('newsletter.nova.group') ?? 'Mailings');
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            Email::make('Email')->sortable(),

            Text::make('Name')
                ->sortable()
                ->nullable(),

            Text::make('Token')
                ->copyable()
                ->onlyOnDetail(),

            Badge::make(__('Verified'), fn (): string => $this->verified_at !== null ? 'verified' : 'unverified')
                ->map([
                    'verified' => 'success',
                    'unverified' => 'warning',
                ])->labels([
                    'verified' => __('Verified'),
                    'unverified' => __('Pending'),
                ]),

            Text::make(__('Mailings Received'), fn (): int => $this->mailingRecipients()->whereNotNull('sent_at')->count())
                ->exceptOnForms(),

            DateTime::make('Verified At')
                ->sortable()
                ->hideFromIndex()
                ->nullable(),

            DateTime::make(__('Created At'))
                ->sortable()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->readonly(),

            DateTime::make(__('Updated At'))
                ->sortable()
                ->onlyOnDetail(),
        ];
    }

    /**
     * @return array<int, SubscriberVerifiedFilter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            new SubscriberVerifiedFilter,
        ];
    }
}
