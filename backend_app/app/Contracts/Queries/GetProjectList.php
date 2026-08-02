<?php

declare(strict_types=1);

namespace App\Contracts\Queries;

use App\Models\User;
use App\Models\Workspace;

interface GetProjectList
{
    /**
     * @return array<string,mixed>
     */
    public function execute(Workspace $workspace, User $viewer, int $page = 1, int $perPage = 15): array;
}
