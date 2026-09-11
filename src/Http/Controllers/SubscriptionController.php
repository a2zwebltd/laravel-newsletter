<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Http\Controllers;

use A2ZWeb\Newsletter\Events\SubscriberUnsubscribed;
use A2ZWeb\Newsletter\Events\SubscriberVerified;
use A2ZWeb\Newsletter\Mail\NewsletterVerificationMail;
use A2ZWeb\Newsletter\Models\MailingSubscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public const ONE_CLICK_FIELD = 'List-Unsubscribe';

    public const ONE_CLICK_VALUE = 'One-Click';

    /**
     * Subscribe an email address. Always answers 200 with the same message so
     * the endpoint cannot be used to tell which addresses are known:
     *  - registered user: handed to the `subscribe_existing_user` callback
     *    (or treated as a subscriber when `subscribe.registered_users` is
     *    'subscriber');
     *  - known verified subscriber: nothing changes, no mail;
     *  - known unsubscribed subscriber: `unsubscribed_at` cleared, address has
     *    to confirm again (verification mail sent);
     *  - known unverified subscriber: verification mail re-sent;
     *  - new address: pending subscriber created, verification mail sent.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $rules = [
            'email' => config('newsletter.validation.email', 'required|email:rfc,dns|max:255'),
            'source' => 'nullable|string|max:64',
        ];

        $captchaRule = config('newsletter.routes.captcha_rule');
        if ($captchaRule) {
            $rules[config('newsletter.routes.captcha_field', 'g-recaptcha-response')] = 'required|'.$captchaRule;
        }

        $request->validate($rules);

        $email = strtolower(trim((string) $request->input('email')));
        $response = response()->json(['message' => $this->subscribedMessage()]);

        $user = $this->treatsRegisteredUsersAsSubscribers() ? null : $this->findUserByEmail($email);
        if ($user) {
            $callback = $this->resolveCallable(config('newsletter.callbacks.subscribe_existing_user'));
            if ($callback !== null) {
                $callback($user);
            }

            return $response;
        }

        $subscriber = MailingSubscriber::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($subscriber instanceof MailingSubscriber) {
            if ($subscriber->isUnsubscribed()) {
                $subscriber->markAsSubscribed();
            }

            if (! $subscriber->isVerified()) {
                $this->sendVerificationMail($subscriber);
            }

            return $response;
        }

        $subscriber = MailingSubscriber::create([
            'email' => $email,
            'source' => Str::limit(trim((string) $request->input('source', '')), 64, '') ?: 'website',
        ]);

        $this->sendVerificationMail($subscriber);

        return $response;
    }

    public function verify(string $token): RedirectResponse|View
    {
        $subscriber = MailingSubscriber::where('token', $token)->firstOrFail();

        if (! $subscriber->isVerified()) {
            $subscriber->update(['verified_at' => now()]);

            event(new SubscriberVerified($subscriber));
        }

        $view = config('newsletter.views.verified');
        if (is_string($view) && $view !== '') {
            return view($view, ['subscriber' => $subscriber]);
        }

        return redirect()
            ->route(config('newsletter.redirects.after_verify', 'home'))
            ->with('message', __('Email verified!'));
    }

    public function showUnsubscribe(Request $request, string $uuid): mixed
    {
        if (! Str::isUuid($uuid)) {
            abort(404);
        }

        $user = $this->findUserByUuid($uuid);
        $subscriber = MailingSubscriber::where('token', $uuid)->first();
        $unsubscribed = $request->has('unsubscribed')
            || (! $subscriber && ! $user)
            || ($subscriber instanceof MailingSubscriber && $subscriber->isUnsubscribed());

        $view = config('newsletter.views.unsubscribed');

        return view(is_string($view) && $view !== '' ? $view : 'newsletter::web.unsubscribe', [
            'uuid' => $uuid,
            'unsubscribed' => $unsubscribed,
        ]);
    }

    /**
     * Browser confirmation (CSRF-protected form on the unsubscribe page).
     */
    public function confirmUnsubscribe(string $uuid): RedirectResponse
    {
        $this->unsubscribe($uuid);

        return redirect()
            ->route('unsubscribe.show', ['uuid' => $uuid, 'unsubscribed' => true])
            ->with('status', __('You have successfully unsubscribed.'));
    }

    /**
     * RFC 8058 endpoint referenced by the List-Unsubscribe header. Mail
     * clients POST `List-Unsubscribe=One-Click` here without a CSRF token
     * (the route runs without the CSRF middleware); a plain GET from a client
     * that opened the link is sent to the confirmation page instead.
     */
    public function oneClickUnsubscribe(Request $request, string $uuid): Response|RedirectResponse
    {
        if (! $request->isMethod('POST')) {
            return redirect()->route('unsubscribe.show', ['uuid' => $uuid]);
        }

        if (! $this->isOneClickRequest($request)) {
            return response('', Response::HTTP_BAD_REQUEST);
        }

        $this->unsubscribe($uuid);

        return response('', Response::HTTP_NO_CONTENT);
    }

    protected function unsubscribe(string $uuid): void
    {
        $user = $this->findUserByUuid($uuid);
        if ($user) {
            $callback = $this->resolveCallable(config('newsletter.callbacks.unsubscribe_user'));
            if ($callback !== null) {
                $callback($user);
            }
        }

        $subscriber = MailingSubscriber::where('token', $uuid)->first();
        if (! $subscriber instanceof MailingSubscriber) {
            return;
        }

        if (config('newsletter.unsubscribe.mode', 'mark') === 'delete') {
            $subscriber->delete();
        } else {
            $subscriber->markAsUnsubscribed();
        }

        event(new SubscriberUnsubscribed($subscriber));
    }

    protected function treatsRegisteredUsersAsSubscribers(): bool
    {
        return config('newsletter.subscribe.registered_users', 'callback') === 'subscriber';
    }

    protected function isOneClickRequest(Request $request): bool
    {
        if ($request->input(self::ONE_CLICK_FIELD) === self::ONE_CLICK_VALUE) {
            return true;
        }

        return str_contains((string) $request->getContent(), self::ONE_CLICK_FIELD.'='.self::ONE_CLICK_VALUE);
    }

    protected function sendVerificationMail(MailingSubscriber $subscriber): void
    {
        Mail::to($subscriber->getEmail())->send(new NewsletterVerificationMail($subscriber->getToken()));
    }

    protected function subscribedMessage(): string
    {
        return __((string) config(
            'newsletter.responses.subscribed',
            'Almost there. Open the e-mail we just sent and confirm the address, so we know it is you.',
        ));
    }

    protected function findUserByEmail(string $email): ?Model
    {
        /** @var class-string<Model> $model */
        $model = config('newsletter.user_model');

        return $model::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }

    protected function findUserByUuid(string $uuid): ?Model
    {
        $callback = $this->resolveCallable(config('newsletter.callbacks.find_user_by_uuid'));
        if ($callback !== null) {
            return $callback($uuid);
        }

        /** @var class-string<Model> $model */
        $model = config('newsletter.user_model');

        return $model::query()->where('uuid', $uuid)->first();
    }

    /**
     * Resolve a config value that may be a closure or an invokable class-string.
     */
    protected function resolveCallable(mixed $value): ?callable
    {
        if (is_string($value) && class_exists($value)) {
            $value = app($value);
        }

        return is_callable($value) ? $value : null;
    }
}
