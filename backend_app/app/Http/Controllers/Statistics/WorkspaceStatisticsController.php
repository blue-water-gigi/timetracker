<?php

declare(strict_types=1);

namespace App\Http\Controllers\Statistics;

use App\Contracts\Queries\Statistics\GetWorkspaceStatistics;
use App\Http\Controllers\Controller;
use App\Http\Requests\Statistics\ShowStatisticsRequest;
use App\Models\Workspace;
use Gate;
use Illuminate\Http\JsonResponse;

class WorkspaceStatisticsController extends Controller
{
    public function __invoke(
        ShowStatisticsRequest $request,
        Workspace $workspace,
        GetWorkspaceStatistics $stats): JsonResponse
    {
        Gate::authorize('viewWorkspaceStatistics', $workspace);

        return response()->json([
            'data' => $stats->execute($workspace, $request->period()),
        ]);
    }
}
