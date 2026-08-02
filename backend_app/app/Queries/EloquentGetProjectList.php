<?php

declare(strict_types=1);

namespace App\Queries;

use App\Contracts\Queries\GetProjectList;
use App\Http\Resources\Project\ProjectCollection;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\Paginator;

class EloquentGetProjectList implements GetProjectList
{
    /**
     * {@inheritDoc}
     */
    public function execute(Workspace $workspace, User $viewer, int $page = 1, int $perPage = 15): array
    {
        $paginator = $this->paginator($workspace, $viewer, $page, $perPage);

        return ProjectCollection::make($paginator)
            ->toResponse(request())
            ->getData(true);
    }

    private function paginator(Workspace $workspace, User $viewer, int $page = 1, int $perPage = 15): Paginator
    {
        return Project::query()->visibleTo($viewer, $workspace)
            ->with(['workspace', 'createdBy', 'updatedBy'])
            ->withCount('memberships')
            ->latest()
            ->paginate(perPage: $perPage, page: $page)
            ->appends([
                'perPage' => $perPage,
            ]);
    }
}
