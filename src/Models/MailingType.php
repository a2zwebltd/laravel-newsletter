<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Models;

use A2ZWeb\Newsletter\Concerns\HasUuid;
use A2ZWeb\Newsletter\Database\Factories\MailingTypeFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property mixed $uuid
 * @property string|null $code
 * @property string|null $name
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MailingType extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $guarded = [
        'id',
        'uuid',
    ];

    protected $fillable = [
        'code',
        'name',
    ];

    protected static function newFactory(): Factory
    {
        return MailingTypeFactory::new();
    }

    /**
     * @return HasMany<Mailing>
     */
    public function mailings(): HasMany
    {
        return $this->hasMany(Mailing::class);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
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
