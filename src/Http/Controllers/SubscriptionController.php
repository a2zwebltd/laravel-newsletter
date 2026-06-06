<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Http\Controllers;

use A2ZWeb\Newsletter\Mail\NewsletterVerificationMail;
use A2ZWeb\Newsletter\Models\MailingSubscriber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    /**
     * Subscribe an email address. Existing registered users are handed to the
     * configured `subscribe_existing_user` callback; otherwise a pending
     * subscriber is created and a verification email is sent.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $rules = ['email' => 'required|email'];

        $captchaRule = config('newsletter.routes.captcha_rule');
        if ($captchaRule) {
            $rules[config('newsletter.routes.captcha_field', 'g-recaptcha-response')] = 'required|'.$captchaRule;
        }

        $request->validate($rules);

        $email = strtolower($request->input('email'));

        $user = $this->findUserByEmail($email);
        if ($user) {
            $callback = $this->resolveCallable(config('newsletter.callbacks.subscribe_existing_user'));
            if ($callback !== null) {
                $callback($user);
            }

            return response()->json([
                'message' => __('Thank you for subscribing to our newsletter. We\'ve just sent you an email to confirm your email address.'),
            ]);
        }

        if (MailingSubscriber::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return response()->json(['message' => __('You are already subscribed to the newsletter!')]);
        }

        /** @var MailingSubscriber $subscriber */
        $subscriber = MailingSubscriber::create(['email' => $email]);

        Mail::to($subscriber->email)->send(new NewsletterVerificationMail($subscriber->getToken()));

        return response()->json(
            ['message' => __('Thank you for subscribing! Please check your email to verify your subscription.')],
            201,
        );
    }

    public function verify(string $token): RedirectResponse
    {
        $subscriber = MailingSubscriber::where('token', $token)->firstOrFail();
        $subscriber->update(['verified_at' => now()]);

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
        $unsubscribed = $request->has('unsubscribed') || (! $subscriber && ! $user);

        return view('newsletter::web.unsubscribe', [
            'uuid' => $uuid,
            'unsubscribed' => $unsubscribed,
        ]);
    }

    public function confirmUnsubscribe(string $uuid): RedirectResponse
    {
        $user = $this->findUserByUuid($uuid);
        if ($user) {
            $callback = $this->resolveCallable(config('newsletter.callbacks.unsubscribe_user'));
            if ($callback !== null) {
                $callback($user);
            }
        }

        $subscriber = MailingSubscriber::where('token', $uuid)->first();
        if ($subscriber instanceof MailingSubscriber) {
            $subscriber->delete();
        }

        return redirect()
            ->route('unsubscribe.show', ['uuid' => $uuid, 'unsubscribed' => true])
            ->with('status', __('You have successfully unsubscribed.'));
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
