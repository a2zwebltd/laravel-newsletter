# Changelog

All notable changes to `a2zwebltd/laravel-newsletter` are documented here.

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
