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
tokenised verification link, and can unsubscribe at any time. Every mailing
carries RFC 2369 / RFC 8058 `List-Unsubscribe` + `List-Unsubscribe-Post`
headers, so Gmail, Yahoo and friends can unsubscribe with one click.

### Subscribe endpoint

`POST newsletter.subscribe` takes `email` (validated with
`newsletter.validation.email`, `required|email:rfc,dns|max:255` by default) and
an optional `source` (string, max 64, stored on the subscriber, defaults to
`website`). It always answers **200** with the same `message`
(`newsletter.responses.subscribed`) no matter whether the address is new,
already subscribed, unsubscribed earlier or belongs to a registered user, so the
form cannot be used to enumerate addresses. What happens behind the scenes:

| Address is… | Effect |
|-------------|--------|
| new | pending subscriber created, verification mail sent |
| a pending (unverified) subscriber | verification mail sent again |
| a verified subscriber | nothing (no mail) |
| an unsubscribed subscriber | `unsubscribed_at` cleared; verification mail only if never verified |
| a registered user | `subscribe.registered_users`: `callback` (default) hands the user to `callbacks.subscribe_existing_user`, no subscriber row; `subscriber` treats the address exactly like a new one |

The route is throttled (`routes.subscribe_middleware`, `['throttle:10,1']` by
default), which also rate-limits the re-sent verification mails.

### Soft unsubscribe

Unsubscribing stamps `unsubscribed_at` on the subscriber and keeps the row
(`unsubscribe.mode => 'mark'`), so the address stays known, is excluded from
every audience (`AudienceResolver::subscribersQuery()`, the
`MailingSubscriber::subscribed()` scope) and subscribing again simply clears
the stamp. Set `unsubscribe.mode => 'delete'` to remove the row as 1.1 did.

### One-click unsubscribe (RFC 8058)

Every mailing carries:

```
List-Unsubscribe: <https://your.app/unsubscribe/{uuid}/one-click>, <mailto:from@your.app?subject=unsubscribe>
List-Unsubscribe-Post: List-Unsubscribe=One-Click
```

Mail clients POST `List-Unsubscribe=One-Click` to the https URI without a CSRF
token, so that URI is a dedicated route, `unsubscribe.one-click`, registered
with `withoutMiddleware(ValidateCsrfToken::class)` (the host's own subclass is
excluded too). It only honours a POST whose body carries
`List-Unsubscribe=One-Click` (204, no body; anything else is 400) and redirects
a plain GET to the confirmation page. The browser flow is untouched:
`unsubscribe.show` renders the confirmation page and `unsubscribe.confirm`
handles its CSRF-protected form.

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
| `nova.register_metrics` | `true` — set to `false` to drop the metric cards from the Mailing resource |
| `routes.*` | Toggle/route prefix, subscribe middleware (`['throttle:10,1']`), captcha rule |
| `validation.email` | Rules for the posted address, `required|email:rfc,dns|max:255` (drop `dns` where there is no outbound DNS) |
| `responses.subscribed` | The one message the subscribe endpoint answers with |
| `subscribe.registered_users` | `callback` (default) or `subscriber` — how a registered user's address is handled, see [Subscribe endpoint](#subscribe-endpoint) |
| `unsubscribe.mode` | `mark` (default, keeps the row with `unsubscribed_at`) or `delete` |
| `views.verified` | View rendered (with `$subscriber`) after verification instead of redirecting to `redirects.after_verify`; `null` redirects |
| `views.unsubscribed` | View for the unsubscribe confirmation page (`$uuid`, `$unsubscribed`); `null` keeps the package view |
| `queue.connection` / `queue.name` | Queue connection and name for `SendMailingJob`, the queued Nova actions and the package mailables when queued (`null` = app defaults) |
| `queue.tries` / `queue.backoff` | Attempts (`3`) and seconds between them (`120`) for `SendMailingJob`; `tries => null` keeps retrying for 24 h as 1.1 did |

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
| `SubscriberVerified` | A subscriber confirmed their address for the first time (welcome mail, CRM sync) |
| `SubscriberUnsubscribed` | A subscriber unsubscribed through the confirmation page or the one-click endpoint (in `delete` mode the model is already deleted) |

`Mailing::approve()` stamps `approved_at`, enrols the audience through
`A2ZWeb\Newsletter\Support\MailingRecipientBuilder` and fires `MailingApproved`;
the Nova action and your own code share that path. Failed deliveries keep the
exception message (500 chars) in `mailing_recipients.failure_reason`, shown on
the Nova recipient detail.

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
| GET, POST | `/unsubscribe/{uuid}/one-click` | `unsubscribe.one-click` (no CSRF, RFC 8058) |

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

## Upgrading

### 1.1 → 1.2

- Run `php artisan migrate`: adds `mailing_subscribers.unsubscribed_at`,
  `mailing_subscribers.source` and `mailing_recipients.failure_reason`
  (guarded, safe on fresh installs).
- Unsubscribing now keeps the subscriber row with `unsubscribed_at` set. If you
  relied on the hard delete, set `'unsubscribe' => ['mode' => 'delete']`.
- The subscribe endpoint answers `200` for every outcome (it used to answer
  `201` for a new address and reveal "already subscribed"). Adjust front-end
  code that branched on the status or message.
- The address is now validated with `email:rfc,dns`; set
  `validation.email` to `required|email|max:255` where outbound DNS is missing.
- The subscribe route is throttled by default (`routes.subscribe_middleware`).
- `SendMailingJob` retries 3 times, 120 s apart, instead of for 24 hours; set
  `queue.tries => null` to keep the old window.
- `Mailing::approve()` now also builds recipients and fires `MailingApproved`
  (and is a no-op on an already approved mailing).

## Testing

```bash
composer test
```

## License

MIT — see LICENSE file.
