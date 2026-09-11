<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Models;

use A2ZWeb\Newsletter\Contracts\CanReceiveMailing;
use A2ZWeb\Newsletter\Database\Factories\MailingSubscriberFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $email
 * @property string|null $name
 * @property string $token
 * @property Carbon|null $verified_at
 * @property Carbon|null $unsubscribed_at
 * @property string|null $source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MailingSubscriber extends Model implements CanReceiveMailing
{
    use HasFactory;

    protected $fillable = ['email', 'token', 'name', 'verified_at', 'unsubscribed_at', 'source'];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'token' => 'string',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (MailingSubscriber $model): void {
            if (empty($model->token)) {
                $model->token = (string) Str::uuid();
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return MailingSubscriberFactory::new();
    }

    /**
     * @return HasMany<MailingRecipient>
     */
    public function mailingRecipients(): HasMany
    {
        return $this->hasMany(MailingRecipient::class, 'subscriber_id');
    }

    /**
     * Verified subscribers that have not unsubscribed: the mailing audience.
     */
    #[Scope]
    protected function subscribed(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at')->whereNull('unsubscribed_at');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isUnsubscribed(): bool
    {
        return $this->unsubscribed_at !== null;
    }

    /**
     * Stamp `unsubscribed_at`; the row is kept so the address stays known.
     */
    public function markAsUnsubscribed(): void
    {
        $this->unsubscribed_at = now();
        $this->save();
    }

    /**
     * Clear `unsubscribed_at` after the address subscribes again. The address
     * has to confirm again: an opt-out is undone only by the person who owns
     * the inbox, never by whoever types the address into the form.
     */
    public function markAsSubscribed(): void
    {
        $this->unsubscribed_at = null;
        $this->verified_at = null;
        $this->save();
    }

    public function getPersonalizedName(): string
    {
        $name = trim($this->name ?? '');

        if (! empty($name)) {
            return explode(' ', $name)[0];
        }

        return Str::before($this->email, '@');
    }

    public function getUuid(): string
    {
        return $this->token;
    }

    public function getMailingEmail(): string
    {
        return $this->getEmail();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getVerifiedAt(): ?CarbonInterface
    {
        return $this->verified_at;
    }

    public function getUnsubscribedAt(): ?CarbonInterface
    {
        return $this->unsubscribed_at;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getCreatedAt(): ?CarbonInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?CarbonInterface
    {
        return $this->updated_at;
    }
}
