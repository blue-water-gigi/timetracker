<?php

declare(strict_types=1);

use App\Contracts\Queries\GetProjectList;
use App\Models\Project;
use App\Models\Workspace;
use App\Queries\EloquentGetProjectList;
use Mockery\MockInterface;
use Tests\Support\TenantFixture;

it('honors the requested page and page size in the Eloquent query', function (): void {
    $tenant = TenantFixture::create();
    Project::factory()->count(4)->for($tenant->workspace)->create();

    $list = app(EloquentGetProjectList::class)->execute(
        $tenant->workspace,
        $tenant->admin,
        page: 2,
        perPage: 2,
    );

    expect($list['meta'])
        ->toMatchArray([
            'current_page' => 2,
            'per_page' => 2,
            'total' => 5,
            'last_page' => 3,
        ])
        ->and($list['data'])->toHaveCount(2);
});

it('keeps the existing HTTP pagination response contract', function (): void {
    $tenant = TenantFixture::create();

    $this->actingAs($tenant->admin)
        ->getJson('/api/v1/workspaces/'.$tenant->workspace->id.'/projects')
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'path',
                'per_page',
                'to',
                'total',
                'resource',
                'includes',
            ],
        ]);
});

it('validates project list pagination parameters', function (array $query, string $field): void {
    $tenant = TenantFixture::create();

    $this->actingAs($tenant->admin)
        ->getJson(
            '/api/v1/workspaces/'.$tenant->workspace->id.'/projects?'.http_build_query($query)
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'page must be positive' => [['page' => 0], 'page'],
    'page size must be positive' => [['perPage' => 0], 'perPage'],
    'page size is limited to fifteen' => [['perPage' => 16], 'perPage'],
]);

it('resolves project index data through the query contract', function (): void {
    $tenant = TenantFixture::create();

    $query = Mockery::mock(GetProjectList::class, function (MockInterface $mock) use ($tenant): void {
        $mock->shouldReceive('execute')
            ->once()
            ->withArgs(fn (Workspace $workspace, $viewer, int $page, int $perPage): bool => (
                $workspace->is($tenant->workspace)
                && $viewer->is($tenant->admin)
                && $page === 2
                && $perPage === 15
            ))
            ->andReturn([
                'data' => [],
                'meta' => ['queryContractWasUsed' => true],
            ]);
    });
    $this->app->instance(GetProjectList::class, $query);

    $this->actingAs($tenant->admin)
        ->getJson('/api/v1/workspaces/'.$tenant->workspace->id.'/projects?page=2')
        ->assertOk()
        ->assertJsonPath('meta.queryContractWasUsed', true);
});
