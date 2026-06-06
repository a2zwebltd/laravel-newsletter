<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Models;

use A2ZWeb\Newsletter\Concerns\HasUuid;
use A2ZWeb\Newsletter\Database\Factories\MailingRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property mixed $uuid
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 * @property int $mailing_id
 * @property int|null $user_id
 * @property int|null $subscriber_id
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Mailing|null $mailing
 * @property-read MailingSubscriber|null $subscriber
 */
class MailingRecipient extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = [
        'id',
        'uuid',
    ];

    protected $fillable = [
        'sent_at',
        'failed_at',
        'mailing_id',
        'user_id',
        'subscriber_id',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return MailingRecipientFactory::new();
    }

    public function mailing(): BelongsTo
    {
        return $this->belongsTo(Mailing::class);
    }

    #[Scope]
    protected function sent(Builder $query): Builder
    {
        return $query->whereNotNull('sent_at');
    }

    #[Scope]
    protected function failed(Builder $query): Builder
    {
        return $query->whereNotNull('failed_at');
    }

    public function user(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('newsletter.user_model');

        return $this->belongsTo($userModel);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(MailingSubscriber::class, 'subscriber_id');
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getSentAt(): ?Carbon
    {
        return $this->sent_at;
    }

    public function setSentAt(?Carbon $sent_at): self
    {
        $this->sent_at = $sent_at;

        return $this;
    }

    public function getMailingId(): int
    {
        return $this->mailing_id;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function getCreatedAt(): ?Carbon
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?Carbon
    {
        return $this->updated_at;
    }

    public function getFailedAt(): ?Carbon
    {
        return $this->failed_at;
    }

    public function getSubscriberId(): ?int
    {
        return $this->subscriber_id;
    }
}
