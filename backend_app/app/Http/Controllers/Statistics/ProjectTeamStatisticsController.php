<?php

declare(strict_types=1);

namespace App\Http\Controllers\Statistics;

use App\Contracts\Queries\Statistics\GetProjectTeamStatistics;
use App\Http\Controllers\Controller;
use App\Http\Requests\Statistics\ShowStatisticsRequest;
use App\Models\Project;
use App\Models\Workspace;
use Gate;
use Illuminate\Http\JsonResponse;

class ProjectTeamStatisticsController extends Controller
{
    public function __invoke(
        ShowStatisticsRequest $request,
        Workspace $workspace,
        Project $project,
        GetProjectTeamStatistics $stats): JsonResponse
    {
        Gate::authorize('viewTeamStatistics', $project);

        return response()->json([
            'data' => $stats->execute($workspace, $project, $request->period()),
        ]);
    }
}
