<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Http\Requests\NovaRequest;

class ScheduleMailing extends Action
{
    use Queueable;

    public $name = 'Schedule Mailing';

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        foreach ($models as $mailing) {
            $mailing->scheduled_at = $fields->get('scheduled_at');
            $mailing->saveQuietly();
        }

        return Action::message('Mailing has been scheduled.');
    }

    public function fields(NovaRequest $request): array
    {
        return [
            DateTime::make(__('Scheduled At'), 'scheduled_at')
                ->rules('required', 'after:now'),
        ];
    }

    public function authorizedToRun(Request $request, $model): bool
    {
        return $model->approved_at !== null && $model->sent_at === null;
    }

    public function enabled(bool $value): static
    {
        $this->showOnIndex = false;
        $this->showOnDetail = $value;
        $this->showInline = $value;

        return $this;
    }
}
