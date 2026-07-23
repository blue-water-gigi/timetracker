<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Http\Resources\Organization\OrganizationCollection;
use App\Http\Resources\Organization\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Throwable;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResource
    {
        Gate::authorize('viewAny', Organization::class);

        return new OrganizationCollection(
            $request->user()
                ->ownedOrganizations()
                ->withCount(['workspaces', 'users'])
                ->with('owner')
                ->paginate(10)
                ->withQueryString()
        );
    }

    public function store(StoreOrganizationRequest $request): JsonResource
    {
        Gate::authorize('create', Organization::class);

        $organization = $request->user()
            ->ownedOrganizations()
            ->create($request->validated());

        return new OrganizationResource($organization->load('owner'));
    }

    public function show(Organization $organization): JsonResource
    {
        Gate::authorize('view', $organization);

        return new OrganizationResource($organization->load('owner')
            ->loadCount(['workspaces', 'users'])
        );
    }

    /** @throws Throwable */
    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResource
    {
        Gate::authorize('update', $organization);

        $organization->updateOrFail($request->validated());

        return new OrganizationResource($organization->load('owner'));
    }

    /** @throws Throwable */
    public function destroy(Organization $organization): JsonResponse
    {
        Gate::authorize('delete', $organization);

        $organization->deleteOrFail();

        return response()->json(status: 204);
    }
}
