<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\TimesheetStatus;
use DomainException;
use Illuminate\Contracts\Debug\ShouldntReport;

abstract class TimesheetStateConflict extends DomainException implements ShouldntReport
{
    /**
     * @param  array<TimesheetStatus>  $allowedStatuses
     */
    protected function __construct(
        string $message,
        public readonly TimesheetStatus $currentStatus,
        public readonly array $allowedStatuses)
    {
        parent::__construct($message);
    }

    abstract public function errorCode(): string;

    /**
     * @return array{currentStatus: TimesheetStatus, allowedStatuses: string[]}
     */
    public function context(): array
    {
        return [
            'currentStatus' => $this->currentStatus,
            'allowedStatuses' => array_map(
                fn (TimesheetStatus $status): string => $status->value,
                $this->allowedStatuses
            ),
        ];
    }
}
