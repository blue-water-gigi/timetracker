<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Http\Resources\Organization\OrganizationCollection;
use App\Http\Resources\Organization\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResource
    {
        return new OrganizationCollection(Organization::query()
            ->with('plan')
            ->withCount(['workspaces', 'users'])
            ->paginate(20)
            ->withQueryString()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrganizationRequest $request): JsonResource
    {
        $validated = $request->validated();

        $organization = Organization::query()->create($validated);

        return new OrganizationResource($organization);
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization): JsonResource
    {
        return new OrganizationResource($organization);
    }

    /**
     * Update the specified resource in storage.
     * @throws Throwable
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResource
    {
        $validated = $request->validated();

        $organization->updateOrFail($validated);

        return new OrganizationResource($organization);
    }

    /**
     * Remove the specified resource from storage.
     * @throws Throwable
     */
    public function destroy(Organization $organization): JsonResponse
    {
        $organization->deleteOrFail();

        return response()->json(status: 204);
    }
}
