<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Settings;

use Spatie\LaravelSettings\Settings;

class MailingSettings extends Settings
{
    public ?string $test_email_address = null;

    public ?string $from_email_address = null;

    public ?string $from_name = null;

    public ?int $extra_delay_seconds = null;

    public static function group(): string
    {
        return 'mailing';
    }
}
