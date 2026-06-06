<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Concerns;

use A2ZWeb\Newsletter\Contracts\CanReceiveMailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Apply to the host User model so it satisfies
 * {@see CanReceiveMailing}.
 *
 * Override any of these methods on the model if your column names differ.
 */
trait ReceivesMailings
{
    /**
     * @return HasMany<MailingRecipient>
     */
    public function mailingRecipients(): HasMany
    {
        return $this->hasMany(MailingRecipient::class);
    }

    public function getPersonalizedName(): string
    {
        $name = trim((string) ($this->name ?? ''));

        if ($name !== '') {
            return explode(' ', $name)[0];
        }

        return Str::before((string) $this->email, '@');
    }

    public function getMailingEmail(): string
    {
        return (string) $this->email;
    }

    public function getUuid(): mixed
    {
        return $this->uuid;
    }
}
