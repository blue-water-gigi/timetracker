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
use Throwable;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResource
    {
        return new OrganizationCollection(
            $request->user()
                ->ownedOrganizations()
                ->withCount(['workspaces', 'users'])
                ->paginate(20)
                ->withQueryString()
        );
    }

    public function store(StoreOrganizationRequest $request): JsonResource
    {
        $organization = $request->user()
            ->ownedOrganizations()
            ->create($request->validated());

        return new OrganizationResource($organization);
    }

    public function show(Organization $organization): JsonResource
    {
        return new OrganizationResource($organization);
    }

    /** @throws Throwable */
    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResource
    {
        $organization->updateOrFail($request->validated());

        return new OrganizationResource($organization);
    }

    /** @throws Throwable */
    public function destroy(Organization $organization): JsonResponse
    {
        $organization->deleteOrFail();

        return response()->json(status: 204);
    }
}
