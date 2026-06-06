<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;

return [

    /*
    |--------------------------------------------------------------------------
    | User model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model representing registered users (mailing recipients that
    | are not anonymous subscribers). Defaults to your application's configured
    | auth model. Apply the A2ZWeb\Newsletter\Concerns\ReceivesMailings trait to
    | it so it satisfies the CanReceiveMailing contract.
    |
    */

    'user_model' => env('NEWSLETTER_USER_MODEL', config('auth.providers.users.model', 'App\\Models\\User')),

    /*
    |--------------------------------------------------------------------------
    | Send rate limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of individual emails dispatched per minute. Drives both the
    | named 'mailings' rate limiter applied to SendMailingJob and the spacing of
    | scheduled-send dispatches.
    |
    */

    'per_minute' => (int) env('NEWSLETTER_PER_MINUTE', 12),

    /*
    |--------------------------------------------------------------------------
    | Mail rendering
    |--------------------------------------------------------------------------
    |
    | email_view_prefix: dotted view path (without the trailing template name)
    |   used to render a mailing's body. The Mailing's `template` column is
    |   appended, e.g. "newsletter::emails.mailings" + "mailing". Point this at
    |   your own app views (e.g. "emails.mailings") to use branded templates.
    |
    | template_glob: filesystem glob used by the Nova template picker to list the
    |   available *.blade.php templates.
    |
    | inline_css_path: optional absolute path to a compiled CSS file whose rules
    |   are inlined into the rendered HTML. Null (or a missing file) skips
    |   inlining.
    |
    */

    'email_view_prefix' => env('NEWSLETTER_EMAIL_VIEW_PREFIX', 'newsletter::emails.mailings'),

    'template_glob' => resource_path('views/vendor/newsletter/emails/mailings/*.blade.php'),

    'inline_css_path' => env('NEWSLETTER_INLINE_CSS_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The package ships two route groups:
    |  - subscription lifecycle (subscribe / verify / unsubscribe)
    |  - public archive pages (index / form / item / browser preview)
    |
    | Each can be toggled independently. `subscribe_middleware` lets you inject a
    | captcha / throttle middleware in front of the public subscribe endpoint.
    | `captcha_rule` is the validation rule appended to the captcha field (set to
    | null to disable captcha validation entirely).
    |
    */

    'routes' => [
        'enabled' => true,
        'archive_enabled' => true,
        'middleware' => ['web'],
        'subscribe_middleware' => [],
        'captcha_field' => 'g-recaptcha-response',
        'captcha_rule' => env('NEWSLETTER_CAPTCHA_RULE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect routes
    |--------------------------------------------------------------------------
    |
    | Named routes used for redirects after verification / unsubscribe. These
    | must exist in the host application.
    |
    */

    'redirects' => [
        'after_verify' => env('NEWSLETTER_AFTER_VERIFY_ROUTE', 'home'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audience
    |--------------------------------------------------------------------------
    |
    | Determines which registered users are enrolled as recipients when a mailing
    | is approved, and how the "potential recipients" metric is computed.
    |
    | `users_query` receives a fresh query builder for the user model and should
    | constrain it to eligible recipients. The default targets users that have
    | verified their email. Provide a closure to add your own opt-in scope, e.g.
    | ->where('marketing', true).
    |
    | `type_scopes` maps a MailingType `code` to an additional closure applied on
    | top of `users_query`, so different mailing types can target different
    | audiences (e.g. "verified_consent" only marketing opt-ins).
    |
    */

    'audience' => [
        // Optional closure (fn (Builder $q) => Builder) to scope eligible users.
        // Leave null to use the default (verified users). NOTE: closures are not
        // config-cacheable — set this from a service provider's boot() at runtime
        // (e.g. config(['newsletter.audience.users_query' => fn ($q) => ...])) so
        // `php artisan config:cache` keeps working.
        'users_query' => null,

        // Optional per-mailing-type closures, same caveat as above.
        'type_scopes' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Host callbacks
    |--------------------------------------------------------------------------
    |
    | Hooks that bridge the package to host-specific user behaviour. Each is an
    | optional callable; leave null to no-op.
    |
    | subscribe_existing_user: fn ($user) => void — invoked when someone
    |   subscribes with an email that already belongs to a registered user
    |   (e.g. flip a marketing opt-in flag).
    |
    | unsubscribe_user: fn ($user) => void — invoked when a registered user
    |   unsubscribes (e.g. clear the marketing opt-in flag).
    |
    | find_user_by_uuid: fn (string $uuid) => ?Model — resolve a registered
    |   user from the public uuid used in unsubscribe links. Defaults to
    |   `User::where('uuid', $uuid)`; override if you store uuids differently
    |   (e.g. binary/efficient uuids).
    |
    */

    'callbacks' => [
        'subscribe_existing_user' => null,
        'unsubscribe_user' => null,
        'find_user_by_uuid' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | CTA short links
    |--------------------------------------------------------------------------
    |
    | When enabled and ashallendesign/short-url is installed, the CTA URL of a
    | saved mailing is automatically shortened into `cta_url_short`.
    |
    */

    'short_url' => [
        'enabled' => env('NEWSLETTER_SHORT_URL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    |
    | When enabled the package self-registers the `mailings:send-scheduled`
    | command on the scheduler (every minute) to dispatch mailings whose
    | scheduled_at has elapsed.
    |
    */

    'schedule' => [
        'send_scheduled' => env('NEWSLETTER_SCHEDULE_SEND', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Nova
    |--------------------------------------------------------------------------
    */

    'nova' => [
        'register_resources' => true,
        'group' => 'Mailings',
        // Nova resource class used for User BelongsTo fields, when Nova is present.
        'user_resource' => 'App\\Nova\\User',
    ],

];
