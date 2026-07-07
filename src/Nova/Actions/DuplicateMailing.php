<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova\Actions;

use A2ZWeb\Newsletter\Models\Mailing;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Clones a mailing (content, CTA, template, type) into a fresh draft so an
 * admin can quickly base a new send on a previous one. Approval / send /
 * schedule timestamps, slug and uuid are reset on the copy.
 */
class DuplicateMailing extends Action
{
    use Queueable;

    public $name = 'Duplicate Mailing';

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        foreach ($models as $mailing) {
            /** @var Mailing $copy */
            $copy = $mailing->replicate(['uuid', 'slug', 'approved_at', 'sent_at', 'scheduled_at', 'cta_url_short']);
            $copy->title = trim((string) $mailing->title).' ('.__('Copy').')';
            $copy->approved_at = null;
            $copy->sent_at = null;
            $copy->scheduled_at = null;
            $copy->cta_url_short = null;
            $copy->save();
        }

        return Action::message(__('Mailing duplicated as a new draft.'));
    }

    public function fields(NovaRequest $request): array
    {
        return [];
    }

    public function enabled(bool $value): static
    {
        $this->showOnIndex = false;
        $this->showOnDetail = $value;
        $this->showInline = $value;

        return $this;
    }
}
