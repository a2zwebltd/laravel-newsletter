# Changelog

All notable changes to `a2zwebltd/laravel-newsletter` are documented here.

## 1.4.0 - 2026-11-07

### Security
- `POST newsletter.subscribe` no longer opts a registered user into marketing mail just because someone typed their address. In the default `subscribe.registered_users` = `callback` mode, `callbacks.subscribe_existing_user` now runs at once only when the signed-in user posts their own address. Any other request for a registered address gets the normal double opt-in: a pending subscriber and a verification mail. The click runs the callback and deletes the subscriber row (after `SubscriberVerified`), so the user stays reachable only through `users_query`.

## 1.3.1 - 2026-09-23

### Fixed
- One-click unsubscribe (`unsubscribe.one-click`) returned 419 to mail clients on Laravel 13. Its `web` group runs `PreventRequestForgery`, the parent of `ValidateCsrfToken` and `VerifyCsrfToken`, and the route only excluded those two. The route now also excludes `PreventRequestForgery` when it exists; Laravel 11 and 12 are unchanged. Hosts that re-declare the route (`routes.enabled` false) must exclude it too; the skill shows the form.

## 1.3.0 - 2026-09-23

### Added
- Laravel Boost resources: `resources/boost/guidelines/core.blade.php` (the must-know rules) and the `newsletter-development` skill (install, audience and callbacks, subscribe contract, custom routes and pages, templates, queues, events, Nova, upgrading, testing).
- A log warning when a mailing goes to a recipient without a `uuid` (the unsubscribe link and `List-Unsubscribe` header are left out), and when neither `unsubscribe.one-click` nor `unsubscribe.show` is registered. Sending is unchanged.

### Changed
- `config/newsletter.php` and the README document the hooks (`audience.users_query`, `audience.type_scopes.*`, `callbacks.*`) as invokable class-strings with their `__invoke` signatures. The old advice to set closures with `config()` at runtime is gone.
- The bundled `mailing` template checks `Route::has()` before linking to `mailing` and `unsubscribe.show`, so it renders when the routes are disabled.

## 1.2.0 - 2026-09-11

### Added
- `SubscriberVerified` (first verification only) and `SubscriberUnsubscribed` events.
- Soft unsubscribe: `mailing_subscribers.unsubscribed_at`, `unsubscribe.mode` (`mark` default, `delete` keeps the 1.1 behaviour), `MailingSubscriber::subscribed()` scope, `isUnsubscribed()` / `markAsUnsubscribed()` / `markAsSubscribed()`, Nova "Unsubscribed" boolean and filter. Subscribing again clears the stamp.
- One subscribe response: HTTP 200 with the same `responses.subscribed` message for new, pending, verified, unsubscribed and registered addresses (no enumeration). Pending subscribers get the verification mail again.
- `subscribe.registered_users` (`callback` default / `subscriber`): let a registered user's address go through the same double opt-in as anyone else.
- `validation.email` (`required|email:rfc,dns|max:255`), `routes.subscribe_middleware` defaults to `['throttle:10,1']`, optional `source` input stored on the subscriber.
- Queue settings `queue.connection`, `queue.name`, `queue.tries` (3), `queue.backoff` (120) honoured by `SendMailingJob`, the queued Nova actions and the package mailables.
- RFC 8058 one-click unsubscribe: `List-Unsubscribe` (https + mailto) and `List-Unsubscribe-Post` headers on every mailing, new CSRF-free `unsubscribe.one-click` route (POST with `List-Unsubscribe=One-Click` → 204, GET → confirmation page).
- `mailing_recipients.failure_reason` written on job failure and when no address resolves; shown on the Nova recipient detail.
- `Mailing::approve()` builds recipients (`Support\MailingRecipientBuilder`) and fires `MailingApproved`; the Nova action uses it.
- `views.verified` and `views.unsubscribed` to render host views.
- `nova.register_metrics` to drop the metric cards.

### Changed
- `SendMailingJob` retries 3 times, 120 s apart, by default; `queue.tries => null` restores the 24-hour window.
- `Mailing::approve()` is a no-op on an already approved mailing.
- An unsubscribed address that subscribes again has to confirm again (`markAsSubscribed()` clears `verified_at` too); the form cannot re-enrol somebody who opted out.
- The `mailing_types` migration leaves an existing table untouched instead of seeding into it (a host's own table of another shape no longer breaks `migrate`).

### Fixed
- `Mailing::isSent()` / `isScheduled()` and the `getSentAt()` / `getScheduledAt()` return types accept `CarbonImmutable` (hosts using `Date::use(CarbonImmutable::class)` saw a sent mailing as unsent).

### Upgrade
- Run `php artisan migrate`.
- Hosts that relied on the hard delete on unsubscribe: set `unsubscribe.mode => 'delete'`.
