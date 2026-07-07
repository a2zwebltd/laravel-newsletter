<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova;

use A2ZWeb\Newsletter\Models\MailingRecipient as MailingRecipientModel;
use A2ZWeb\Newsletter\Nova\Filters\RecipientStatusFilter;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

class MailingRecipient extends Resource
{
    public static string $model = MailingRecipientModel::class;

    public static $title = 'id';

    public static $perPageViaRelationship = 10;

    public static $search = [
        'id',
    ];

    public static function group(): string
    {
        return (string) (config('newsletter.nova.group') ?? 'Mailings');
    }

    public function fields(NovaRequest $request): array
    {
        return array_values(array_filter([
            ID::make()->sortable(),

            Text::make(__('Mailing'), fn () => '['.$this->mailing?->getId().'] '.substr((string) $this->mailing?->getTitle(), 0, 32).'..')
                ->onlyOnIndex(),

            BelongsTo::make(__('Mailing'), 'mailing', Mailing::class)
                ->sortable()
                ->hideFromIndex()
                ->filterable()
                ->rules('required'),

            Badge::make(__('Status'), fn (): string => match (true) {
                $this->sent_at !== null => 'sent',
                $this->failed_at !== null => 'failed',
                default => 'pending',
            })->map([
                'pending' => 'warning',
                'sent' => 'success',
                'failed' => 'danger',
            ])->labels([
                'pending' => __('Pending'),
                'sent' => __('Sent'),
                'failed' => __('Failed'),
            ]),

            Text::make(__('Recipient'), fn (): string => (string) ($this->user?->email ?? $this->subscriber?->email ?? '—'))
                ->onlyOnIndex(),

            Text::make(__('Recipient'), fn (): string => (string) ($this->user?->email ?? $this->subscriber?->email ?? '—'))
                ->onlyOnDetail()
                ->copyable(),

            Text::make(__('Recipient Name'), fn (): string => (string) ($this->user?->name ?? $this->subscriber?->name ?? '—'))
                ->exceptOnForms(),

            DateTime::make(__('Sent At'), 'sent_at')
                ->sortable()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->nullable(),

            DateTime::make(__('Failed At'), 'failed_at')
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->sortable()
                ->nullable(),

            DateTime::make(__('Created At'))
                ->sortable()
                ->onlyOnDetail(),

            DateTime::make(__('Updated At'))
                ->sortable()
                ->onlyOnDetail(),

            Text::make(__('UUID'))
                ->onlyOnDetail()
                ->copyable(),

            $this->userField(),

            BelongsTo::make(__('Subscriber'), 'subscriber', MailingSubscriber::class)
                ->sortable()
                ->filterable()
                ->nullable(),
        ]));
    }

    /**
     * @return array<int, RecipientStatusFilter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            new RecipientStatusFilter,
        ];
    }

    /**
     * BelongsTo to the host's User Nova resource, when configured and present.
     */
    protected function userField(): ?Field
    {
        /** @var class-string|null $userResource */
        $userResource = config('newsletter.nova.user_resource');

        if (! $userResource || ! class_exists($userResource)) {
            return null;
        }

        return BelongsTo::make(__('User'), 'user', $userResource)
            ->sortable()
            ->filterable()
            ->nullable();
    }
}
