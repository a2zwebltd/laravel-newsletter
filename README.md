# Laravel Newsletter

A portable Laravel newsletter / broadcast-mailing engine. Admin-authored mailings are delivered to your registered users **and** anonymous subscribers through an approval → schedule → send workflow, with per-recipient delivery tracking, a double opt-in subscribe / verify / unsubscribe flow, queued sending with rate limiting, customizable mail templates, and ready-made Nova admin resources.

Designed to drop into any Laravel app with minimal wiring while staying fully customisable — the host application keeps control of its user model, audience rules, branding and notifications.

## Requirements

- PHP 8.2+
- Laravel 11 / 12 / 13
- `spatie/laravel-settings` ^3 (sender settings)
- `cviebrock/eloquent-sluggable` (mailing slugs)
- `dyrynda/laravel-model-uuid` (efficient UUIDs)
- `laravel/nova` ^5 (optional — auto-registers Nova resources when present)
- `ashallendesign/short-url` (optional — CTA link shortening)

## Installation

```bash
composer require a2zwebltd/laravel-newsletter
php artisan migrate
php artisan vendor:publish --tag=newsletter-config   # optional
php artisan vendor:publish --tag=newsletter-views     # optional, to rebrand templates
```

Add the trait to your `User` model so it can receive mailings:

```php
use A2ZWeb\Newsletter\Concerns\ReceivesMailings;
use A2ZWeb\Newsletter\Contracts\CanReceiveMailing;

class User extends Authenticatable implements CanReceiveMailing
{
    use ReceivesMailings;
}
```

Register the sender settings class with `spatie/laravel-settings` (the package
auto-discovers it, but you can also add it explicitly in `config/settings.php`):

```php
'settings' => [
    \A2ZWeb\Newsletter\Settings\MailingSettings::class,
],
```

## How it works

1. **Author** a `Mailing` in Nova (title, markdown content, optional CTA, template).
2. **Approve** it — recipients are generated from the configured audience
   (eligible users + verified subscribers) and `MailingApproved` fires.
3. **Send now** or **Schedule** — a `SendMailingJob` is queued per recipient,
   rate-limited via the `mailings` limiter (`newsletter.per_minute`).
4. On delivery, `MailingDelivered` fires so you can mirror the mailing into an
   in-app inbox, analytics, etc.

Anonymous visitors subscribe through `newsletter.subscribe`, confirm via a
tokenised verification link, and can unsubscribe at any time. A standards
compliant `List-Unsubscribe` header is added to every mailing.

## Configuration

See `config/newsletter.php`. Highlights:

| Key | Purpose |
|-----|---------|
| `user_model` | Your application's user model |
| `per_minute` | Send-rate limit (drives the `mailings` limiter) |
| `email_view_prefix` | View path for mailing bodies — point at your own branded templates |
| `audience.users_query` | Closure scoping eligible registered users |
| `audience.type_scopes` | Per-mailing-type audience refinements |
| `callbacks.*` | Hooks for existing-user subscribe / unsubscribe / uuid lookup |
| `short_url.enabled` | Auto-shorten CTA links when `ashallendesign/short-url` is installed |
| `nova.group` / `nova.user_resource` | Nova menu group and the User resource used for relations |
| `routes.*` | Toggle/route prefix, subscribe middleware, captcha rule |

## Events

| Event | When |
|-------|------|
| `MailingSaved` | A mailing is saved (CTA shortening, cache busting) |
| `MailingApproved` | A mailing is approved and recipients generated (SEO/IndexNow) |
| `MailingDelivered` | An email was delivered to a recipient (in-app notifications) |

## Routes

| Method | URI | Name |
|--------|-----|------|
| GET | `/newsletters` | `newsletters` |
| GET | `/newsletter_form` | `newsletters.form` |
| GET | `/newsletters/{slug}` | `newsletters.item` |
| GET | `/mailing/{slug}` | `mailing` |
| POST | `/newsletter/subscribe` | `newsletter.subscribe` |
| GET | `/newsletter/verify/{token}` | `newsletter.verify` |
| GET | `/unsubscribe/{uuid}` | `unsubscribe.show` |
| POST | `/unsubscribe/{uuid}` | `unsubscribe.confirm` |

The archive group can be disabled (`newsletter.routes.archive_enabled`) if your
app renders its own newsletter pages.

## Cron

The package self-registers the scheduled-send command. To run it yourself
instead, disable `newsletter.schedule.send_scheduled` and add:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('mailings:send-scheduled')->everyMinute();
```

## Testing

```bash
composer test
```

## License

MIT — see LICENSE file.
