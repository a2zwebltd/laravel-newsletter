<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Mail;

use A2ZWeb\Newsletter\Contracts\CanReceiveMailing;
use A2ZWeb\Newsletter\Models\Mailing;
use A2ZWeb\Newsletter\Models\MailingRecipient;
use A2ZWeb\Newsletter\Settings\MailingSettings;
use A2ZWeb\Newsletter\Support\NewsletterQueue;
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
    ) {
        NewsletterQueue::applyTo($this);
    }

    public function build(): static
    {
        $nameTag = '{name}';
        $recipientModel = $this->resolveRecipientModel();
        $personalizedName = $recipientModel?->getPersonalizedName() ?: 'there';
        // Keep this nullable (not ''): test sends and anonymous recipients have no uuid, and a
        // template guarding the unsubscribe link with isset($user_uuid) would otherwise pass on
        // an empty string and call route('unsubscribe.show', ['uuid' => '']) → missing-parameter.
        $user_uuid = $recipientModel?->getUuid() ?: null;

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
        $fromAddress = (string) ($settings->from_email_address ?: config('mail.from.address'));

        return $this
            ->from($fromAddress, $settings->from_name ?: config('mail.from.name'))
            ->subject($subject)
            ->withSymfonyMessage(function ($message) use ($user_uuid, $fromAddress): void {
                $this->addListUnsubscribeHeaders($message, $user_uuid, $fromAddress);
            })
            ->html($html);
    }

    /**
     * RFC 2369 / RFC 8058 headers: the https URI answers a one-click POST
     * (`unsubscribe.one-click`, no CSRF) and redirects a plain GET to the
     * confirmation page; the mailto is the fallback for older clients.
     */
    protected function addListUnsubscribeHeaders(object $message, mixed $userUuid, string $fromAddress): void
    {
        if (empty($userUuid)) {
            return;
        }

        $uris = [];

        if (Route::has('unsubscribe.one-click')) {
            $uris[] = '<'.route('unsubscribe.one-click', ['uuid' => $userUuid]).'>';
        } elseif (Route::has('unsubscribe.show')) {
            $uris[] = '<'.route('unsubscribe.show', ['uuid' => $userUuid]).'>';
        }

        if ($fromAddress !== '') {
            $uris[] = '<mailto:'.$fromAddress.'?subject=unsubscribe>';
        }

        if ($uris === []) {
            return;
        }

        $headers = $message->getHeaders();
        $headers->addTextHeader('List-Unsubscribe', implode(', ', $uris));

        if (Route::has('unsubscribe.one-click')) {
            $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }
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
