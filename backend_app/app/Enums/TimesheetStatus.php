<?php

declare(strict_types=1);

namespace App\Enums;

enum TimesheetStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
