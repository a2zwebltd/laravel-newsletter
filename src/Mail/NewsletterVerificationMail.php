<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsletterVerificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $token;

    public string $verificationUrl;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->verificationUrl = route('newsletter.verify', ['token' => $token]);
    }

    public function build(): self
    {
        return $this
            ->subject(config('app.name').__(' - Verify your email address'))
            ->markdown('newsletter::emails.newsletter.verify', [
                'verificationUrl' => $this->verificationUrl,
            ]);
    }
}
