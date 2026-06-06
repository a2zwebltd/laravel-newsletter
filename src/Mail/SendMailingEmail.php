<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Mail;

use A2ZWeb\Newsletter\Contracts\CanReceiveMailing;
use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use A2ZWeb\Newsletter\Settings\MailingSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Markdown;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Route;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class SendMailingEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Mailing $mailing,
        public ?MailingRecipient $recipient = null,
    ) {}

    public function build(): static
    {
        $nameTag = '{name}';
        $recipientModel = $this->resolveRecipientModel();
        $personalizedName = $recipientModel?->getPersonalizedName() ?: 'there';
        $user_uuid = $recipientModel?->getUuid() ?? '';

        $subject = str_replace($nameTag, $personalizedName, $this->mailing->getTitle() ?? '');
        $content = str_replace($nameTag, $personalizedName, $this->mailing->getContent() ?? '');
        $contentExtra = str_replace($nameTag, $personalizedName, $this->mailing->getContentExtra() ?? '');

        $viewPrefix = (string) config('newsletter.email_view_prefix', 'newsletter::emails.mailings');
        $markdown = app()->make(Markdown::class);

        $html = (string) $markdown->render($viewPrefix.'.'.$this->mailing->getTemplate(), [
            'recipient' => $this->recipient,
            'name' => $personalizedName,
            'subject' => $subject,
            'content' => $content,
            'content_extra' => $contentExtra,
            'slug' => $this->mailing->getSlug(),
            'cta_url' => $this->mailing->getCtaUrlShort(),
            'cta_content' => $this->mailing->getCtaContent() ?? '',
            'user_uuid' => $user_uuid,
            'extra_html' => $this->mailing->getExtraHtml() ?? '',
            'mailing_type_id' => $this->mailing->mailing_type_id,
        ]);

        $html = $this->inlineCss($html);

        if ($this->mailing->getReplyTo()) {
            $this->replyTo($this->mailing->getReplyTo());
        }

        $settings = app(MailingSettings::class);

        return $this
            ->from(
                $settings->from_email_address ?: config('mail.from.address'),
                $settings->from_name ?: config('mail.from.name'),
            )
            ->subject($subject)
            ->withSymfonyMessage(function ($message) use ($user_uuid): void {
                if (! empty($user_uuid) && Route::has('unsubscribe.show')) {
                    $unsubscribeGetUrl = route('unsubscribe.show', ['uuid' => $user_uuid]);
                    $message->getHeaders()->addTextHeader('List-Unsubscribe', "<$unsubscribeGetUrl>");
                }
            })
            ->html($html);
    }

    /**
     * Resolve the User or MailingSubscriber the mailing is addressed to.
     */
    protected function resolveRecipientModel(): ?CanReceiveMailing
    {
        if ($this->recipient?->user instanceof CanReceiveMailing) {
            return $this->recipient->user;
        }

        if ($this->recipient?->subscriber instanceof CanReceiveMailing) {
            return $this->recipient->subscriber;
        }

        return null;
    }

    /**
     * Inline a compiled CSS file into the HTML when configured.
     */
    protected function inlineCss(string $html): string
    {
        $cssPath = config('newsletter.inline_css_path');

        if (! $cssPath || ! is_file($cssPath)) {
            return $html;
        }

        $css = (string) file_get_contents($cssPath);
        $css = (string) preg_replace('/display\s*:\s*none\s*!?important\s*;?/i', '', $css);

        return (new CssToInlineStyles)->convert($html, $css);
    }
}
