<?php

declare(strict_types=1);

namespace App\Enums;

enum TimesheetStatus: string
{
    case DRAFT     = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED  = 'approved';
    case REJECTED  = 'rejected';

    public static function editable(): array
    {
        return [self::DRAFT, self::REJECTED];
    }

    public static function reviewDecisions(): array
    {
        return [self::APPROVED, self::REJECTED];
    }

    public static function validTimesheets(): array
    {
        return [self::DRAFT, self::SUBMITTED];
    }

    public function isEditable(): bool
    {
        return in_array($this, self::editable(), true);
    }

    public function canSubmit(): bool
    {
        return $this->canTransitionTo(self::SUBMITTED);
    }

    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::DRAFT, self::REJECTED => $to === self::SUBMITTED,
            self::SUBMITTED             => in_array($to, self::reviewDecisions(), true),
            self::APPROVED              => false,
        };
    }
}
