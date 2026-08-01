<?php

declare(strict_types=1);

namespace App\Contracts\Cache;

interface WorkspaceCacheInvalidator
{
    public function invalidate(int $workspaceId): void;
}
