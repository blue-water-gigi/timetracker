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

    public static function projectList(int $workspaceId, int $viewerId, int $page = 1, int $perPage = 15): string
    {
        return "v1:workspace:{$workspaceId}:project-list:viewer:{$viewerId}:page:{$page}:per-page:{$perPage}";
    }

    public static function projectTag(int $workspaceId): string
    {
        return "workspace:{$workspaceId}:project-list";
    }
}
