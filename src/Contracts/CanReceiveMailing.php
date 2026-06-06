<?php

declare(strict_types=1);

namespace A2ZWeb\Newsletter\Contracts;

interface CanReceiveMailing
{
    /**
     * A short, friendly name used to personalise the {name} placeholder.
     */
    public function getPersonalizedName(): string;

    /**
     * The email address the mailing should be delivered to.
     */
    public function getMailingEmail(): string;

    /**
     * A stable public identifier used for unsubscribe links.
     */
    public function getUuid(): mixed;
}
