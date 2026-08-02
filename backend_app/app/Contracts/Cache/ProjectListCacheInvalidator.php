<?php

declare(strict_types=1);

namespace App\Contracts\Cache;

interface ProjectListCacheInvalidator
{
    public function invalidate(int $workspaceId): void;
}
