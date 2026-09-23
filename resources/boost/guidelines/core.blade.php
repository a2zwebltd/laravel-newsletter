@verbatim
## Laravel Newsletter (a2zwebltd/laravel-newsletter)

Broadcast mailings to registered users and double opt-in subscribers: approve, schedule, send, with one-click unsubscribe.

- Hooks in `config/newsletter.php` (`audience.users_query`, `audience.type_scopes.*`, `callbacks.*`) are invokable class-strings, never closures, so `config:cache` works.
- `users_query` returns the Builder with no `select()` or `orderBy()` (the recipient builder runs `select('id')->chunkById()`). Users and subscribers are not de-duplicated: exclude `MailingSubscriber::query()->subscribed()->select('email')`.
- The User model needs `ReceivesMailings` + `CanReceiveMailing` and a populated `uuid` column. Without a uuid the mail goes out with no unsubscribe link and no `List-Unsubscribe` header (only a log warning).
- With `routes.enabled` false, re-declare EVERY package route under the same names, including `unsubscribe.one-click` (GET+POST, without CSRF middleware).
- The package schedules `mailings:send-scheduled` itself; do not schedule it again.
- A `queue.name` (`NEWSLETTER_QUEUE`) needs a worker or Horizon supervisor listening on it, or nothing is sent.
- Approve in code with `Mailing::approve()`; it enrols recipients and fires `MailingApproved`.
- Keep `email_view_prefix` and `template_glob` pointing at the same template folder.
- `inline_css_path` must be literal CSS (px, hex), never Tailwind v4 output (`var()`, `oklch()`, `@layer`).
- In tests set `NEWSLETTER_EMAIL_RULES` without `dns` (e.g. `required|email|max:255`).

For integration details, use the `newsletter-development` skill.
@endverbatim
