<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case FREE = 'free';
    case ACTIVE = 'active';
    case TRIAL = 'trial';
    case PAST_DUE = 'past_due';
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';
}
