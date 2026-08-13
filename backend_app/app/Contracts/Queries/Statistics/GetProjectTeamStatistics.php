<?php

declare(strict_types=1);

namespace App\Contracts\Queries\Statistics;

use App\Models\Project;
use App\Models\Workspace;
use App\Support\Statistics\StatisticsPeriod;

interface GetProjectTeamStatistics
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Workspace $workspace, Project $project, StatisticsPeriod $period): array;
}
