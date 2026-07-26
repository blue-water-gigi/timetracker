<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use DomainException;
use Illuminate\Contracts\Debug\ShouldntReport;

final class ProjectMembershipRequiredException extends DomainException implements ShouldntReport
{
    public static function make(): self
    {
        return new self('An active project membership is required.');
    }

    public function errorCode(): string
    {
        return 'active_project_membership_required';
    }
}
