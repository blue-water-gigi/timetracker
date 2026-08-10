<?php

declare(strict_types=1);

namespace App\Contracts\Queries\Statistics;

use App\Models\Workspace;
use App\Support\Statistics\StatisticsPeriod;

interface GetWorkspaceStatistics
{
    /**
     * @param  StatisticsPeriod  $period  DTO
     * @return array<string,mixed>
     */
    public function execute(Workspace $workspace, StatisticsPeriod $period): array;
}
