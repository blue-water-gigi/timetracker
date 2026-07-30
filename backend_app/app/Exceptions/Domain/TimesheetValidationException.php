<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use DomainException;
use Illuminate\Contracts\Debug\ShouldntReport;

abstract class TimesheetValidationException extends DomainException implements ShouldntReport
{
    protected function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }

    abstract public function errorCode(): string;

    final public function errors(): array
    {
        return [
            $this->field => [$this->getMessage()],
        ];
    }
}
