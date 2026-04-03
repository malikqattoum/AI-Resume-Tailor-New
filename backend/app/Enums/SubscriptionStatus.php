<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Canceled = 'canceled';
    case PastDue = 'past_due';
    case Trialing = 'trialing';

    public function isActive(): bool
    {
        return $this === self::Active || $this === self::Trialing;
    }
}
