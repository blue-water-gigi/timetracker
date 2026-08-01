<?php

declare(strict_types=1);

namespace App\Contracts\Queries;

use App\Models\User;
use App\Models\Workspace;

interface GetWorkspaceSummary
{
    /**
     * @return array<string,int>
     */
    public function execute(Workspace $workspace, User $viewer): array;
}
