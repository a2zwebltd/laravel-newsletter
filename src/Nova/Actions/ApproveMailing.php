<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class ApproveMailing extends Action
{
    use Queueable;

    public $name = 'Approve Mailing';

    public function handle(ActionFields $fields, Collection $models): ActionResponse|Action
    {
        foreach ($models as $mailing) {
            $mailing->approve();
        }

        return Action::message('Mailing approved successfully and recipients have been created.');
    }

    public function fields(NovaRequest $request): array
    {
        return [];
    }

    public function authorizedToRun($request, $model): bool
    {
        return $model->approved_at === null;
    }

    public function enabled(bool $value): static
    {
        $this->showOnIndex = false;
        $this->showOnDetail = $value;
        $this->showInline = $value;

        return $this;
    }
}
