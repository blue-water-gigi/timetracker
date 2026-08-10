<?php

declare(strict_types=1);

namespace App\Http\Controllers\Statistics;

use App\Contracts\Queries\Statistics\GetPersonalStatistics;
use App\Http\Controllers\Controller;
use App\Http\Requests\Statistics\ShowStatisticsRequest;
use App\Models\User;
use App\Models\Workspace;
use Gate;
use Illuminate\Http\JsonResponse;

class PersonalStatisticsController extends Controller
{
    public function __invoke(
        ShowStatisticsRequest $request,
        Workspace $workspace,
        GetPersonalStatistics $stats): JsonResponse
    {
        Gate::authorize('viewPersonalStatistics', $workspace);

        /** @var User $viewer */
        $viewer = $request->user();

        return response()->json([
            'data' => $stats->execute($workspace, $viewer, $request->period()),
        ]);
    }
}
