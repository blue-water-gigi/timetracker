<?php

declare(strict_types=1);

namespace App\Support\Cache;

class CacheKeys
{
    public static function workspaceSummary(int $workspaceId, int $viewerId): string
    {
        return "v1:workspace:{$workspaceId}:viewer:{$viewerId}:summary";
    }

    public static function workspaceTag(int $workspaceId): string
    {
        return "workspace:{$workspaceId}";
    }
}
