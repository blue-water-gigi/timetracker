<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Contracts\Cache\ProjectListCacheInvalidator;

class NullProjectListCacheInvalidator implements ProjectListCacheInvalidator
{
    public function invalidate(int $workspaceId): void {}
}
