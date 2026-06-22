# Laravel Newsletter

[![Packagist Version](https://img.shields.io/packagist/v/a2zwebltd/laravel-newsletter.svg)](https://packagist.org/packages/a2zwebltd/laravel-newsletter)
[![Downloads](https://img.shields.io/packagist/dt/a2zwebltd/laravel-newsletter.svg)](https://packagist.org/packages/a2zwebltd/laravel-newsletter)
![PHP](https://img.shields.io/badge/PHP-%5E8.2-blue)
![Laravel](https://img.shields.io/badge/Laravel-12%20%7C%2013-blue)

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
| `inline_css_path` | Absolute path to a compiled CSS file inlined into every mailing (`null` = no inlining). See [Email CSS inlining](#email-css-inlining-optional) |
| `audience.users_query` | Closure scoping eligible registered users |
| `audience.type_scopes` | Per-mailing-type audience refinements |
| `callbacks.*` | Hooks for existing-user subscribe / unsubscribe / uuid lookup |
| `short_url.enabled` | Auto-shorten CTA links when `ashallendesign/short-url` is installed |
| `nova.group` / `nova.user_resource` | Nova menu group and the User resource used for relations |
| `routes.*` | Toggle/route prefix, subscribe middleware, captcha rule |

## Email CSS inlining (optional)

The **bundled templates are already self-contained** — every element carries inline `style`
attributes — so they render correctly with **no extra CSS** and `inline_css_path` left at its
default `null`. You only need this feature if you ship **custom templates** that style elements via
CSS classes (e.g. a markdown body whose `<p>`/`<h1>`/`<code>` come from a class rule), since most
email clients strip `<style>`/`<link>`.

To enable it, point `inline_css_path` (or the `NEWSLETTER_INLINE_CSS_PATH` env var) at the
**absolute path of a CSS file**; its rules are inlined onto matching elements at send time:

```env
NEWSLETTER_INLINE_CSS_PATH="${PWD}/public/build/assets/mail.css"
```

**A missing file silently disables inlining — it never throws** — so a broken or skipped build
degrades to plain templates rather than failing the queue job.

> ⚠️ **Do NOT point this at raw Tailwind v4 output.** Tailwind v4 compiles utilities and `@apply`
> into `@layer` rules full of CSS custom properties (`padding: calc(var(--spacing) * 6)`) and
> `oklch()` colors. Those references resolve from `:root`/`@theme`, which email clients strip — so
> once inlined onto an element they **collapse to nothing** (lost padding, fonts, colors). The
> inliner needs **plain, literal CSS**: explicit `px`/`%` and hex/rgb colors, no `var()`, no
> `oklch()`, no `@layer`/`@apply`. Hand-write the email stylesheet (or post-process Tailwind output
> to literal values) and build it before the app serves — it is host-specific, so the package does
> not ship it. A minimal example:

```css
/* resources/css/mail.css — plain, email-safe CSS */
.content-wrap p  { padding: 8px 0; font-size: 16px; line-height: 1.625 !important; }
.content-wrap h1 { padding: 16px 0; font-weight: 700; font-size: 22px; }
.btn-primary     { display: inline-block; background: #4f46e5; color: #fff !important; padding: 12px 20px; border-radius: 8px; }
```

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
