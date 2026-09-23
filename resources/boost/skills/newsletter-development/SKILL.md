---
name: newsletter-development
description: Integrate and customise a2zwebltd/laravel-newsletter (config/newsletter.php, Mailing, MailingSubscriber, MailingRecipient, SendMailingEmail, SendMailingJob, mailings:send-scheduled). Use when wiring the audience or host callbacks, the subscribe / verify / unsubscribe flow, custom routes or pages, branded mail templates, queues, newsletter events, the Nova resources and metrics, upgrading, or testing newsletter code.
---

# Newsletter development

## When to use this skill

- Installing the package or wiring the User model, audience and callbacks.
- Building a subscribe form, custom verify / unsubscribe pages or your own routes.
- Branding the mailing templates or inlining mail CSS.
- Routing campaigns to a queue / Horizon lane, listening to newsletter events.
- Adding the Nova metrics to a dashboard, upgrading, writing tests.

## Install / wiring checklist

1. `composer require a2zwebltd/laravel-newsletter`
2. spatie/laravel-settings needs its `settings` table. If the host has none: `php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider" --tag="migrations"`. The package adds its own settings migration (`MailingSettings`, group `mailing`) to `settings.migrations_paths` and auto-discovers the class.
3. Give `users` a public `uuid` column, backfill it, and fill it on create. The unsubscribe link and `List-Unsubscribe` header use it:

```php
// migration
Schema::table('users', fn (Blueprint $table) => $table->uuid('uuid')->nullable()->unique()->after('id'));
DB::table('users')->whereNull('uuid')->orderBy('id')->pluck('id')
    ->each(fn (int $id) => DB::table('users')->where('id', $id)->update(['uuid' => (string) Str::uuid()]));
```

4. The User model:

```php
use A2ZWeb\Newsletter\Concerns\ReceivesMailings;
use A2ZWeb\Newsletter\Contracts\CanReceiveMailing;

class User extends Authenticatable implements CanReceiveMailing
{
    use ReceivesMailings; // getPersonalizedName(), getMailingEmail(), getUuid(), mailingRecipients()

    protected static function booted(): void
    {
        static::creating(fn (User $user) => $user->uuid ??= (string) Str::uuid());
    }
}
```

5. `php artisan migrate` (creates `mailing_types` seeded with `verified_users` / `verified_consent`, `mailings`, `mailing_subscribers`, `mailing_recipients`, plus the settings).
6. `php artisan vendor:publish --tag=newsletter-config` and set the hooks (below). `--tag=newsletter-views` copies the views to `resources/views/vendor/newsletter` for rebranding.
7. A route named `home` must exist, or set `redirects.after_verify` / `views.verified` (see Recipes).
8. Run a queue worker for the configured `queue.connection` / `queue.name`.

## API & config reference

Hooks: every one is an invokable class-string, resolved with `app($class)` when it runs. Closures break `config:cache`.

| Key | `__invoke` signature | Default when null |
|-----|----------------------|-------------------|
| `audience.users_query` | `(Builder $query): Builder` | `whereNotNull('email_verified_at')` |
| `audience.type_scopes.<mailing type code>` | `(Builder $query): Builder`, applied after `users_query` | none |
| `callbacks.subscribe_existing_user` | `(User $user): void` | no-op |
| `callbacks.unsubscribe_user` | `(User $user): void` | no-op |
| `callbacks.find_user_by_uuid` | `(string $uuid): ?User` | `user_model::where('uuid', $uuid)->first()` |

Other keys (`config/newsletter.php`):

| Key | Default | Meaning |
|-----|---------|---------|
| `user_model` | `auth.providers.users.model` | Registered-user model |
| `per_minute` | `12` (`NEWSLETTER_PER_MINUTE`) | Dispatch spacing and the `mailings` rate limiter |
| `email_view_prefix` | `newsletter::emails.mailings` | View prefix; the mailing's `template` column is appended |
| `template_glob` | `resource_path('views/vendor/newsletter/emails/mailings/*.blade.php')` | Nova template picker; files starting with `_` are hidden |
| `inline_css_path` | `null` (`NEWSLETTER_INLINE_CSS_PATH`) | Absolute CSS file inlined into mailings; missing file = no inlining |
| `routes.enabled` / `routes.archive_enabled` | `true` / `true` | Load the package routes / the archive pages |
| `routes.middleware` | `['web']` | Group middleware |
| `routes.subscribe_middleware` | `['throttle:10,1']` | Extra middleware on `newsletter.subscribe` |
| `routes.captcha_field` / `routes.captcha_rule` | `g-recaptcha-response` / `NEWSLETTER_CAPTCHA_RULE` | Captcha validation, off when the rule is null |
| `validation.email` | `required\|email:rfc,dns\|max:255` (`NEWSLETTER_EMAIL_RULES`) | Subscribe address rules |
| `responses.subscribed` | "Almost there. …" | The single subscribe response message |
| `subscribe.registered_users` | `callback` (`NEWSLETTER_REGISTERED_USERS`) | `callback` or `subscriber` |
| `unsubscribe.mode` | `mark` (`NEWSLETTER_UNSUBSCRIBE_MODE`) | `mark` stamps `unsubscribed_at`, `delete` removes the row |
| `views.verified` / `views.unsubscribed` | `null` | Host views for the verify / unsubscribe pages |
| `redirects.after_verify` | `home` (`NEWSLETTER_AFTER_VERIFY_ROUTE`) | Route name used when `views.verified` is null |
| `queue.connection` / `queue.name` | `null` (`NEWSLETTER_QUEUE_CONNECTION` / `NEWSLETTER_QUEUE`) | Used by `SendMailingJob`, the queued Nova actions and the mailables |
| `queue.tries` / `queue.backoff` | `3` / `120` | `SendMailingJob` attempts; `tries => null` retries for 24 h |
| `short_url.enabled` | `true` | Shorten `cta_url` into `cta_url_short` when ashallendesign/short-url is installed |
| `schedule.send_scheduled` | `true` | Package schedules `mailings:send-scheduled` every minute |
| `nova.register_resources` / `nova.register_metrics` | `true` / `true` | Nova resources / metric cards on the Mailing resource |
| `nova.group` / `nova.user_resource` | `Mailings` / `App\Nova\User` | Nova menu group / User resource for BelongsTo |

Models and code entry points:

- `Mailing::approve()`: stamps `approved_at`, enrols recipients through `Support\MailingRecipientBuilder::build()` (idempotent, returns the count created) and fires `MailingApproved`. No-op when already approved.
- `Support\AudienceResolver`: `usersQuery(?string $typeCode = null)`, `subscribersQuery()` (verified, not unsubscribed).
- `MailingSubscriber`: `subscribed()` scope, `isVerified()`, `isUnsubscribed()`, `markAsUnsubscribed()`, `markAsSubscribed()` (clears `unsubscribed_at` and `verified_at`), `getToken()` (its public id in links).
- `MailingRecipient`: `sent()` / `failed()` scopes, `markAsFailed(?string $reason)`, `failure_reason` (500 chars).
- `Jobs\SendMailingJob($mailing, $recipient)`: one per recipient, rate-limited by the `mailings` limiter.
- Command `mailings:send-scheduled`: sends approved mailings whose `scheduled_at` has passed.

Routes (`routes/web.php`, all inside `routes.middleware`):

| Method | URI | Name | Loaded when |
|--------|-----|------|-------------|
| GET | `/newsletters` | `newsletters` | `archive_enabled` |
| GET | `/newsletter_form` | `newsletters.form` | `archive_enabled` |
| GET | `/newsletters/{slug}` | `newsletters.item` | `archive_enabled` |
| GET | `/mailing/{slug}` | `mailing` | `archive_enabled` |
| POST | `/newsletter/subscribe` | `newsletter.subscribe` | always |
| GET | `/newsletter/verify/{token}` | `newsletter.verify` | always |
| GET | `/unsubscribe/{uuid}` | `unsubscribe.show` | always |
| POST | `/unsubscribe/{uuid}` | `unsubscribe.confirm` | always |
| GET, POST | `/unsubscribe/{uuid}/one-click` | `unsubscribe.one-click` (no CSRF) | always |

Events (`A2ZWeb\Newsletter\Events`):

| Event | Payload | Fired |
|-------|---------|-------|
| `MailingSaved` | `$mailing` | Mailing saved (the short-URL listener uses it) |
| `MailingApproved` | `$mailing` | After `approve()` built the recipients |
| `MailingDelivered` | `$mailing`, `$recipient`, `?CanReceiveMailing $recipientModel` | After each successful send |
| `SubscriberVerified` | `$subscriber` | First verification only |
| `SubscriberUnsubscribed` | `$subscriber` | Subscriber unsubscribed (already deleted in `delete` mode). Not fired for users: use `callbacks.unsubscribe_user` |

Nova (registered only when Nova is installed): resources `A2ZWeb\Newsletter\Nova\{Mailing, MailingType, MailingSubscriber, MailingRecipient}`; actions in `A2ZWeb\Newsletter\Nova\Actions`: `ApproveMailing`, `ScheduleMailing`, `SendMailing`, `SendTestMailing`, `DuplicateMailing`, `ResendFailedRecipients`; metrics in `A2ZWeb\Newsletter\Nova\Metrics`: `PotentialNewsletterRecipients`, `TotalMailingsSent`, `MailingsSentPerMonth`, `EmailsSentPerMonth`, `UniqueRecipientsPerMonth`. The test address, sender and extra delay live in `MailingSettings` (`test_email_address`, `from_email_address`, `from_name`, `extra_delay_seconds`).

## Recipes

### Audience and callbacks

Put them in `app/Support/Newsletter/` and reference the classes in the config:

```php
'audience' => [
    'users_query' => \App\Support\Newsletter\ConsentingUsers::class,
    'type_scopes' => [],
],
'callbacks' => [
    'subscribe_existing_user' => \App\Support\Newsletter\EnableConsentOnSubscribe::class,
    'unsubscribe_user' => \App\Support\Newsletter\DisableConsentOnUnsubscribe::class,
    'find_user_by_uuid' => \App\Support\Newsletter\FindUserByUuid::class,
],
```

```php
namespace App\Support\Newsletter;

use A2ZWeb\Newsletter\Models\MailingSubscriber;
use Illuminate\Database\Eloquent\Builder;

class ConsentingUsers
{
    // No select()/orderBy(): the builder runs select('id')->chunkById(500).
    public function __invoke(Builder $query): Builder
    {
        return $query
            ->whereNotNull('email_verified_at')
            ->whereNotNull('newsletter_consent_at')
            // Users and subscribers are enrolled separately: skip addresses already on the list.
            ->whereNotIn('email', MailingSubscriber::query()->subscribed()->select('email'));
    }
}

class EnableConsentOnSubscribe
{
    public function __invoke(User $user): void
    {
        $user->forceFill(['newsletter_consent_at' => now()])->save();
    }
}

class DisableConsentOnUnsubscribe
{
    public function __invoke(User $user): void
    {
        $user->forceFill(['newsletter_consent_at' => null])->save();
    }
}

class FindUserByUuid
{
    public function __invoke(string $uuid): ?User
    {
        return User::query()->where('uuid', $uuid)->first();
    }
}
```

### The subscribe endpoint

`POST newsletter.subscribe` with `email` and optional `source` (max 64, default `website`). It ALWAYS answers 200 `{"message": responses.subscribed}` so it cannot reveal which addresses are known. Behind it:

- new address: pending `MailingSubscriber`, verification mail (`NewsletterVerificationMail`, link to `newsletter.verify`);
- pending subscriber: verification mail again; verified subscriber: nothing;
- unsubscribed subscriber: `markAsSubscribed()` then a new verification mail;
- registered user: `registered_users = callback` calls `subscribe_existing_user` and creates no subscriber row (reach them through `users_query`); `subscriber` treats the address like any other. Use `subscriber` when `users_query` does not mail users, or an account holder who signs up in the footer never gets anything.

Front-end code must not branch on the status code or the message. The package's form partial (`newsletter::partials.newsletter-form`) posts JSON with the CSRF token.

### Custom routes (`routes.enabled` false)

The package then registers nothing, but its mails, pages and controllers still call these names. Re-declare every route, same names:

```php
use A2ZWeb\Newsletter\Http\Controllers\NewsletterController;
use A2ZWeb\Newsletter\Http\Controllers\SubscriptionController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

Route::middleware('web')->group(function () {
    Route::get('/newsletters', [NewsletterController::class, 'index'])->name('newsletters');
    Route::get('/newsletter_form', [NewsletterController::class, 'form'])->name('newsletters.form');
    Route::get('/newsletters/{slug}', [NewsletterController::class, 'item'])->name('newsletters.item');
    Route::get('/mailing/{slug}', [NewsletterController::class, 'preview'])->name('mailing');

    Route::post('/newsletter/subscribe', [SubscriptionController::class, 'subscribe'])
        ->middleware('throttle:10,1')->name('newsletter.subscribe');
    Route::get('/newsletter/verify/{token}', [SubscriptionController::class, 'verify'])->name('newsletter.verify');
    Route::get('/unsubscribe/{uuid}', [SubscriptionController::class, 'showUnsubscribe'])->name('unsubscribe.show');
    Route::post('/unsubscribe/{uuid}', [SubscriptionController::class, 'confirmUnsubscribe'])->name('unsubscribe.confirm');
    Route::match(['GET', 'POST'], '/unsubscribe/{uuid}/one-click', [SubscriptionController::class, 'oneClickUnsubscribe'])
        // Mail clients POST without a token (RFC 8058). Laravel 13's web group runs
        // PreventRequestForgery, Laravel 11/12 run ValidateCsrfToken; exclude whichever exist.
        ->withoutMiddleware(array_values(array_filter(
            [PreventRequestForgery::class, VerifyCsrfToken::class, ValidateCsrfToken::class],
            fn (string $middleware): bool => class_exists($middleware),
        )))
        ->name('unsubscribe.one-click');
});
```

Without `unsubscribe.one-click` the `List-Unsubscribe` header falls back to the page URL and `List-Unsubscribe-Post` is dropped; without either unsubscribe route only the mailto remains and a warning is logged. To drop only the archive pages, keep `routes.enabled` and set `routes.archive_enabled` false.

### Custom verify / unsubscribe pages

- `views.verified`: rendered with `$subscriber` after verification. When null the user is redirected to the route named in `redirects.after_verify` (default `home`, which must exist) with a `message` flash.
- `views.unsubscribed`: rendered with `$uuid` and `$unsubscribed` (bool). While `$unsubscribed` is false, show a form that POSTs to `route('unsubscribe.confirm', ['uuid' => $uuid])` with `@csrf`.

### Branded templates

- Publish views, edit `resources/views/vendor/newsletter/emails/mailings/*.blade.php` (the default prefix resolves there). If you move templates elsewhere, change `email_view_prefix` AND `template_glob` together, or Nova offers templates that do not render.
- Templates render through `Illuminate\Mail\Markdown` with `$recipient`, `$name`, `$subject`, `$content`, `$content_extra`, `$slug`, `$cta_url`, `$cta_content`, `$user_uuid`, `$extra_html`, `$mailing_type_id`. `{name}` in title/content is replaced with the first name.
- `$user_uuid` is null on a test send and for a user without a uuid. Guard the link: `@if (! empty($user_uuid) && Route::has('unsubscribe.show'))`. `isset()` is not enough in older copies of the template.
- CSS classes need `inline_css_path` pointing at a plain, literal stylesheet (px, hex/rgb). Tailwind v4 output (`var(--spacing)`, `oklch()`, `@layer`) collapses to nothing when inlined. Build or copy the file before the app serves traffic.

### Queue / Horizon

Set `NEWSLETTER_QUEUE=mailings` and give it a supervisor, e.g. in `config/horizon.php`:

```php
'supervisor-mailings' => ['connection' => 'redis', 'queue' => ['mailings'], 'maxProcesses' => 1, 'tries' => 3],
```

Sends are spaced by `60 / per_minute` seconds at dispatch. Do not add `mailings:send-scheduled` to `routes/console.php` unless `schedule.send_scheduled` is false.

### Events

```php
Event::listen(SubscriberVerified::class, fn (SubscriberVerified $e) => Mail::to($e->subscriber->getEmail())->queue(new WelcomeMail));
Event::listen(MailingDelivered::class, fn (MailingDelivered $e) => /* $e->recipientModel is the User or MailingSubscriber */);
```

## Gotchas

- Closures in `config/newsletter.php` (or pushed in with `config([...])`) break `config:cache` or vanish from it. Use invokable classes.
- `users_query` with `select()` / `orderBy()` breaks `chunkById`; without the subscriber exclusion an address that is both a user and a subscriber gets every campaign twice.
- A user with an empty `uuid` still receives the mailing but without an unsubscribe link or `List-Unsubscribe` header; the package only logs a warning.
- Registered users unsubscribe through `callbacks.unsubscribe_user`. With no callback nothing changes for them and no event fires.
- `validation.email` uses `dns` by default: CI without outbound DNS rejects every address.
- `mailing_types` migration skips an existing table, so a host table of another shape is left as it is.

## Upgrading 1.1 → 1.2

- `php artisan migrate` (adds `mailing_subscribers.unsubscribed_at`, `mailing_subscribers.source`, `mailing_recipients.failure_reason`).
- Unsubscribe keeps the row (`unsubscribe.mode = mark`); set `delete` for the old behaviour.
- The subscribe endpoint answers 200 for every outcome (was 201 for new and revealed "already subscribed"): fix front-end branching.
- Email validation adds `dns`; the subscribe route is throttled by default.
- `SendMailingJob` retries 3 times, 120 s apart (`queue.tries => null` for the old 24 h window).
- `Mailing::approve()` now builds recipients and fires `MailingApproved` (no-op when already approved). Host code that built recipients or fired the event after approving can drop that step.
- An unsubscribed address that subscribes again must confirm again.

## Testing

- In `phpunit.xml`: `<env name="NEWSLETTER_EMAIL_RULES" value="required|email|max:255"/>` (no `dns`), and a sync queue or `Queue::fake()`.
- Subscribe: `$this->postJson(route('newsletter.subscribe'), ['email' => 'a@example.com'])->assertOk();` then `Mail::assertSent(NewsletterVerificationMail::class)` with `Mail::fake()`.
- Audience: create users, `$mailing->approve()`, assert `$mailing->mailingRecipients()->count()`; or call `app(AudienceResolver::class)->usersQuery()->count()`.
- Delivery: `SendMailingJob::dispatchSync($mailing, $recipient)` and `Event::assertDispatched(MailingDelivered::class)`, or send `new SendMailingEmail($mailing, $recipient)` to the `array` mailer and read the `List-Unsubscribe` header.
- One-click: `$this->post(route('unsubscribe.one-click', ['uuid' => $uuid]), ['List-Unsubscribe' => 'One-Click'])->assertNoContent();`
- User factories must set `uuid`.
