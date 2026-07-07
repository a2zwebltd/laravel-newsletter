<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Nova;

use A2ZWeb\Newsletter\Models\Mailing as MailingModel;
use A2ZWeb\Newsletter\Nova\Actions\ApproveMailing;
use A2ZWeb\Newsletter\Nova\Actions\DuplicateMailing;
use A2ZWeb\Newsletter\Nova\Actions\ResendFailedRecipients;
use A2ZWeb\Newsletter\Nova\Actions\ScheduleMailing;
use A2ZWeb\Newsletter\Nova\Actions\SendMailing;
use A2ZWeb\Newsletter\Nova\Actions\SendTestMailing;
use A2ZWeb\Newsletter\Nova\Filters\MailingStatusFilter;
use A2ZWeb\Newsletter\Nova\Metrics\EmailsSentPerMonth;
use A2ZWeb\Newsletter\Nova\Metrics\MailingsSentPerMonth;
use A2ZWeb\Newsletter\Nova\Metrics\PotentialNewsletterRecipients;
use A2ZWeb\Newsletter\Nova\Metrics\TotalMailingsSent;
use A2ZWeb\Newsletter\Nova\Metrics\UniqueRecipientsPerMonth;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/**
 * @mixin MailingModel
 */
class Mailing extends Resource
{
    public static string $model = MailingModel::class;

    public static $title = 'title';

    public static $perPageViaRelationship = 10;

    public static $search = [
        'id',
        'title',
        'slug',
        'content',
        'content_extra',
        'cta_content',
        'reply_to',
    ];

    public static function group(): string
    {
        return (string) (config('newsletter.nova.group') ?? 'Mailings');
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make(__('Title'), 'title')
                ->sortable()
                ->rules('required', 'max:255'),

            Badge::make(__('Status'), fn (): string => match (true) {
                $this->sent_at !== null => 'sent',
                $this->scheduled_at !== null => 'scheduled',
                $this->approved_at !== null => 'approved',
                default => 'draft',
            })->map([
                'draft' => 'info',
                'approved' => 'warning',
                'scheduled' => 'warning',
                'sent' => 'success',
            ])->labels([
                'draft' => __('Draft'),
                'approved' => __('Approved'),
                'scheduled' => __('Scheduled'),
                'sent' => __('Sent'),
            ])->hideWhenCreating()->hideWhenUpdating(),

            Text::make(__('📨'), fn () => $this->mailingRecipients()->count())
                ->hideWhenUpdating()
                ->hideWhenCreating(),

            Text::make(__('📤'), fn () => $this->mailingRecipients()->sent()->count())
                ->hideWhenUpdating()
                ->hideWhenCreating(),

            Text::make(__('❌'), fn () => $this->mailingRecipients()->failed()->count())
                ->hideWhenUpdating()
                ->hideWhenCreating(),

            URL::make(__('URL'), fn () => Route::has('mailing') ? route('mailing', ['slug' => $this->getSlug()]) : null)
                ->displayUsing(fn () => '🔗'),

            Select::make(__('Template'), 'template')
                ->options(function () {
                    $files = File::glob((string) config('newsletter.template_glob'));

                    return collect($files)
                        ->map(fn (string $path) => basename($path, '.blade.php'))
                        ->filter(fn (string $name) => ! str_starts_with($name, '_'))
                        ->mapWithKeys(fn (string $name) => [$name => $name])
                        ->toArray() ?: ['mailing' => 'mailing'];
                })
                ->default('mailing')
                ->rules('required')
                ->sortable(),

            BelongsTo::make(__('Mailing Type'), 'mailingType', MailingType::class)
                ->sortable()
                ->withoutTrashed()
                ->hideFromIndex()
                ->rules('required'),

            DateTime::make(__('Approved At'), 'approved_at')
                ->sortable()
                ->hideWhenCreating()
                ->readonly(),

            DateTime::make(__('Sent At'), 'sent_at')
                ->sortable()
                ->hideWhenCreating()
                ->readonly(),

            DateTime::make(__('Scheduled At'), 'scheduled_at')
                ->sortable()
                ->hideWhenCreating()
                ->readonly(),

            Text::make(__('CTA Content'), 'cta_content')
                ->sortable()
                ->hideFromIndex()
                ->rules('max:255'),

            Text::make(__('CTA URL'), 'cta_url')
                ->sortable()
                ->hideFromIndex()
                ->rules('max:255'),

            Text::make(__('CTA URL Short'), 'cta_url_short')
                ->sortable()
                ->onlyOnDetail()
                ->readonly(),

            Text::make(__('Reply To'), 'reply_to')
                ->hideFromIndex()
                ->rules('nullable', 'email', 'max:255'),

            Slug::make(__('Slug'), 'slug')
                ->sortable()
                ->hideFromIndex()
                ->hideWhenCreating()
                ->readonly(),

            $this->markdownField(__('Content'), 'content'),

            $this->markdownField(__('Content Extra'), 'content_extra'),

            Textarea::make(__('Extra HTML'), 'extra_html')
                ->nullable()
                ->hideFromIndex(),

            DateTime::make(__('Created At'))
                ->onlyOnDetail()
                ->sortable(),

            DateTime::make(__('Updated At'))
                ->sortable()
                ->onlyOnDetail(),

            Text::make(__('UUID'))
                ->onlyOnDetail()
                ->copyable(),

            HasMany::make(__('Recipients'), 'mailingRecipients', MailingRecipient::class),
        ];
    }

    /**
     * Use datomatic/nova-markdown-tui when available, falling back to a plain
     * textarea so the package keeps that editor optional.
     */
    protected function markdownField(string $label, string $attribute): mixed
    {
        if (class_exists(MarkdownTui::class)) {
            return MarkdownTui::make($label, $attribute)
                ->fullWidth()
                ->nullable()
                ->hideFromIndex();
        }

        return Textarea::make($label, $attribute)
            ->nullable()
            ->hideFromIndex();
    }

    /**
     * @return array<int, MailingStatusFilter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            new MailingStatusFilter,
        ];
    }

    public function actions(NovaRequest $request): array
    {
        return [
            (new ApproveMailing)->enabled(! $this->isApproved()),
            (new SendMailing)->enabled($this->isApproved() && ! $this->isSent() && ! $this->isScheduled()),
            (new ScheduleMailing)->enabled($this->isApproved() && ! $this->isSent()),
            (new SendTestMailing)->enabled(true),
            (new ResendFailedRecipients)->enabled($this->isSent()),
            (new DuplicateMailing)->enabled(true),
        ];
    }

    public function cards(NovaRequest $request): array
    {
        return [
            PotentialNewsletterRecipients::make()
                ->help(__('Eligible registered users + verified subscribers.'))
                ->width('1/3'),
            TotalMailingsSent::make()->width('1/3'),
            MailingsSentPerMonth::make()->width('1/3'),
            EmailsSentPerMonth::make()->width('1/2'),
            UniqueRecipientsPerMonth::make()->width('1/2'),
        ];
    }
}
