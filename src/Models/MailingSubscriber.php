<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Models;

use A2ZWeb\Newsletter\Contracts\CanReceiveMailing;
use A2ZWeb\Newsletter\Database\Factories\MailingSubscriberFactory;
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MailingSubscriber extends Model implements CanReceiveMailing
{
    use HasFactory;

    protected $fillable = ['email', 'token', 'name', 'verified_at'];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
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

    public function getVerifiedAt(): ?Carbon
    {
        return $this->verified_at;
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?Carbon
    {
        return $this->updated_at;
    }
}
