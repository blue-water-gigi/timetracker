<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Contracts\Cache\WorkspaceCacheInvalidator;

/**
 * @internal only for expected app behavior.
 */
readonly class NullWorkspaceCacheInvalidator implements WorkspaceCacheInvalidator
{
    public function invalidate(int $workspaceId): void {}
}
