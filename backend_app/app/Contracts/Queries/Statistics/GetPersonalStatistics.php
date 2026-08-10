<?php

declare(strict_types=1);

namespace App\Contracts\Queries\Statistics;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Statistics\StatisticsPeriod;

interface GetPersonalStatistics
{
    public function execute(Workspace $workspace, User $viewer, StatisticsPeriod $period): array;
}
