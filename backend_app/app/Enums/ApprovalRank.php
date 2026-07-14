<?php

declare(strict_types=1);

namespace App\Enums;

enum ApprovalRank: int
{
    case PROJECT_LEAD = 3;
    case MANAGER = 2;
    case SENIOR = 1;
    case PARTICIPANT = 0;
}
