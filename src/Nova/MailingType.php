<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova;

use A2ZWeb\Newsletter\Models\MailingType as MailingTypeModel;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

class MailingType extends Resource
{
    public static string $model = MailingTypeModel::class;

    public static $title = 'name';

    public static $search = [
        'id',
        'code',
        'name',
    ];

    public static function group(): string
    {
        return (string) (config('newsletter.nova.group') ?? 'Mailings');
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    public function authorizedToReplicate(Request $request): bool
    {
        return false;
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make(__('Code'), 'code')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make(__('Name'), 'name')
                ->sortable()
                ->rules('required', 'max:255'),

            DateTime::make(__('Created At'))
                ->sortable()
                ->onlyOnDetail(),

            DateTime::make(__('Updated At'))
                ->sortable()
                ->onlyOnDetail(),

            Text::make(__('UUID'))
                ->onlyOnDetail()
                ->copyable(),
        ];
    }
}
