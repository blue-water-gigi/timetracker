<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectRole: string
{
    case PROJECT_LEAD = 'project_lead';
    case MANAGER      = 'manager';
    case SENIOR       = 'senior';
    case PARTICIPANT  = 'participant';

    public function approvalRank(): ApprovalRank
    {
        return match ($this) {
            self::PROJECT_LEAD => ApprovalRank::PROJECT_LEAD,
            self::MANAGER      => ApprovalRank::MANAGER,
            self::SENIOR       => ApprovalRank::SENIOR,
            self::PARTICIPANT  => ApprovalRank::PARTICIPANT,
        };
    }

    /**
     * Get array of management roles of project
     *
     * @return array<int,ProjectRole>
     */
    public static function management(): array
    {
        return [
            self::PROJECT_LEAD,
            self::MANAGER,
            self::SENIOR,
        ];
    }
}
