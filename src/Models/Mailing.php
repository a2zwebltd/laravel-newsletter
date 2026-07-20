<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Models;

use A2ZWeb\Newsletter\Concerns\HasUuid;
use A2ZWeb\Newsletter\Database\Factories\MailingFactory;
use A2ZWeb\Newsletter\Events\MailingSaved;
use Carbon\CarbonInterface;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property mixed $uuid
 * @property int $mailing_type_id
 * @property string $template
 * @property Carbon|null $approved_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $scheduled_at
 * @property string|null $title
 * @property string|null $content
 * @property string|null $content_extra
 * @property string|null $extra_html
 * @property string|null $slug
 * @property string|null $cta_content
 * @property string|null $cta_url
 * @property string|null $cta_url_short
 * @property string|null $reply_to
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MailingType|null $mailingType
 */
class Mailing extends Model
{
    use HasFactory;
    use HasUuid;
    use Sluggable;
    use SoftDeletes;

    protected $guarded = [
        'id',
        'uuid',
    ];

    protected $fillable = [
        'mailing_type_id',
        'template',
        'title',
        'content',
        'content_extra',
        'cta_content',
        'cta_url',
        'cta_url_short',
        'reply_to',
        'extra_html',
        'approved_at',
        'sent_at',
        'scheduled_at',
    ];

    protected $dispatchesEvents = [
        'saved' => MailingSaved::class,
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return MailingFactory::new();
    }

    /**
     * Return the sluggable configuration array for this model.
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }

    /**
     * @return BelongsTo<MailingType, Mailing>
     */
    public function mailingType(): BelongsTo
    {
        return $this->belongsTo(MailingType::class);
    }

    /**
     * @return HasMany<MailingRecipient>
     */
    public function mailingRecipients(): HasMany
    {
        return $this->hasMany(MailingRecipient::class);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getMailingTypeId(): int
    {
        return $this->mailing_type_id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getContentExtra(): ?string
    {
        return $this->content_extra;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getApprovedAt(): ?CarbonInterface
    {
        return $this->approved_at;
    }

    public function isApproved(): bool
    {
        return $this->approved_at instanceof CarbonInterface;
    }

    public function getCreatedAt(): ?CarbonInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?CarbonInterface
    {
        return $this->updated_at;
    }

    public function getCtaContent(): ?string
    {
        return $this->cta_content;
    }

    public function getCtaUrl(): ?string
    {
        return $this->cta_url;
    }

    public function getCtaUrlShort(): ?string
    {
        return $this->cta_url_short;
    }

    public function getReplyTo(): ?string
    {
        return $this->reply_to;
    }

    public function getTemplate(): string
    {
        return $this->template ?? 'mailing';
    }

    public function getExtraHtml(): ?string
    {
        return $this->extra_html;
    }

    public function getSentAt(): ?Carbon
    {
        return $this->sent_at;
    }

    public function getScheduledAt(): ?Carbon
    {
        return $this->scheduled_at;
    }

    public function isSent(): bool
    {
        return $this->sent_at instanceof Carbon;
    }

    public function isScheduled(): bool
    {
        return $this->scheduled_at instanceof Carbon;
    }

    public function approve(): void
    {
        $this->approved_at = now();
        $this->saveQuietly();
    }
}
