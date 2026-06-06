<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Support;

use A2ZWeb\Newsletter\Models\MailingSubscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the eligible recipients for a mailing based on the host-configured
 * audience scopes. Used when generating recipients on approval and by the
 * "potential recipients" Nova metric.
 *
 * Scope callables may be a closure or an invokable class-string (the latter
 * keeps `config/newsletter.php` serializable for `php artisan config:cache`).
 */
class AudienceResolver
{
    /**
     * Eligible registered users, optionally narrowed by a mailing-type code.
     */
    public function usersQuery(?string $typeCode = null): Builder
    {
        /** @var class-string<Model> $model */
        $model = config('newsletter.user_model');

        /** @var Builder $query */
        $query = $model::query();

        $base = $this->resolveCallable(config('newsletter.audience.users_query'));
        if ($base !== null) {
            $query = $base($query);
        } else {
            // Sensible default when no host scope is configured: verified users.
            $query = $query->whereNotNull('email_verified_at');
        }

        if ($typeCode !== null) {
            $scope = $this->resolveCallable(config('newsletter.audience.type_scopes.'.$typeCode));
            if ($scope !== null) {
                $query = $scope($query);
            }
        }

        return $query;
    }

    /**
     * Verified anonymous subscribers.
     *
     * @return Builder<MailingSubscriber>
     */
    public function subscribersQuery(): Builder
    {
        return MailingSubscriber::query()->whereNotNull('verified_at');
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
