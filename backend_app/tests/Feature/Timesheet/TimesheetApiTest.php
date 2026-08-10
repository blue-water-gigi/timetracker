<?php

declare(strict_types=1);

use App\Enums\ProjectRole;
use App\Enums\TimesheetStatus;
use Tests\Support\TenantFixture;

it('allows only active project members to create their own timesheets', function () {
    $tenant = TenantFixture::create();
    $member = $tenant->employee();
    $tenant->membership($member);
    $outsider = $tenant->employee();
    $inactive = $tenant->employee();
    $tenant->membership($inactive, active: false);

    $payload = [
        'period_start' => '2026-07-13',
        'period_end'   => '2026-07-19',
    ];

    $this->actingAs($member)
        ->postJson($tenant->projectUrl('timesheets'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.status', TimesheetStatus::DRAFT->value)
        ->assertJsonPath('data.createdBy.id', $member->id);

    $this->actingAs($outsider)
        ->postJson($tenant->projectUrl('timesheets'), $payload)
        ->assertForbidden();

    $this->actingAs($inactive)
        ->postJson($tenant->projectUrl('timesheets'), $payload)
        ->assertForbidden();
});

it('enforces author ownership and approval-rank workflow', function () {
    $tenant   = TenantFixture::create();
    $author   = $tenant->employee();
    $reviewer = $tenant->employee();
    $tenant->membership($author, ProjectRole::PARTICIPANT);
    $tenant->membership($reviewer, ProjectRole::SENIOR);
    $timesheet = $tenant->timesheet($author);
    $url       = $tenant->projectUrl('timesheets/'.$timesheet->id);

    $this->actingAs($author)
        ->postJson($url.'/submit')
        ->assertOk()
        ->assertJsonPath('data.status', TimesheetStatus::SUBMITTED->value);

    $this->postJson($url.'/approve', [
        'review_comment' => 'Self approval is forbidden.',
    ])->assertForbidden();

    $this->actingAs($reviewer)
        ->postJson($url.'/approve', [
            'review_comment' => 'Approved.',
        ])->assertOk()
        ->assertJsonPath('data.status', TimesheetStatus::APPROVED->value)
        ->assertJsonPath('data.reviewedBy.id', $reviewer->id);
});

it('lets the owning administrator review without project membership', function () {
    $tenant = TenantFixture::create();
    $author = $tenant->employee();
    $tenant->membership($author);
    $timesheet = $tenant->timesheet($author, TimesheetStatus::SUBMITTED);

    $this->actingAs($tenant->admin)
        ->postJson($tenant->projectUrl('timesheets/'.$timesheet->id.'/approve'), [
            'review_comment' => 'Administrator override.',
        ])->assertOk()
        ->assertJsonPath('data.status', TimesheetStatus::APPROVED->value)
        ->assertJsonPath('data.reviewedBy.id', $tenant->admin->id);
});

it('resets review metadata when a rejected timesheet is resubmitted', function () {
    $tenant   = TenantFixture::create();
    $author   = $tenant->employee();
    $reviewer = $tenant->employee();
    $tenant->membership($author, ProjectRole::PARTICIPANT);
    $tenant->membership($reviewer, ProjectRole::SENIOR);
    $timesheet = $tenant->timesheet($author, TimesheetStatus::SUBMITTED);
    $url       = $tenant->projectUrl('timesheets/'.$timesheet->id);

    $this->actingAs($reviewer)
        ->postJson($url.'/reject', [
            'review_comment' => 'Please correct the entries.',
        ])->assertOk()
        ->assertJsonPath('data.status', TimesheetStatus::REJECTED->value);

    $this->actingAs($author)
        ->postJson($url.'/submit')
        ->assertOk()
        ->assertJsonPath('data.status', TimesheetStatus::SUBMITTED->value)
        ->assertJsonMissingPath('data.reviewComment');

    $timesheet->refresh();

    expect($timesheet->reviewed_by_user_id)->toBeNull()
        ->and($timesheet->reviewed_at)->toBeNull()
        ->and($timesheet->review_comment)->toBeNull();
});
it('prevents peers or lower roles from reviewing a higher-rank author', function () {
    $tenant   = TenantFixture::create();
    $author   = $tenant->employee();
    $reviewer = $tenant->employee();
    $tenant->membership($author, ProjectRole::SENIOR);
    $tenant->membership($reviewer, ProjectRole::PARTICIPANT);
    $timesheet = $tenant->timesheet($author, TimesheetStatus::SUBMITTED);

    $this->actingAs($reviewer)
        ->postJson($tenant->projectUrl('timesheets/'.$timesheet->id.'/reject'), [
            'review_comment' => 'Not enough rank.',
        ])->assertForbidden();
});

it('does not shrink a period around existing entries', function () {
    $tenant = TenantFixture::create();
    $author = $tenant->employee();
    $tenant->membership($author);
    $timesheet = $tenant->timesheet($author);
    $timesheet->entries()->create([
        'work_date' => $timesheet->period_start,
        'hours'     => 8,
    ]);

    $this->actingAs($author)
        ->patchJson($tenant->projectUrl('timesheets/'.$timesheet->id), [
            'period_start' => $timesheet->period_start->addDay()->toDateString(),
        ])->assertUnprocessable()
        ->assertJsonPath('data.errorCode', 'invalid_time_entry_period')
        ->assertJsonPath('data.errors.period_start.0', 'Invalid time entry period.');
});

it('shows approvers only submitted timesheets below their rank', function () {
    $tenant   = TenantFixture::create();
    $author   = $tenant->employee();
    $reviewer = $tenant->employee();
    $tenant->membership($author, ProjectRole::PARTICIPANT);
    $tenant->membership($reviewer, ProjectRole::MANAGER);
    $draft     = $tenant->timesheet($author);
    $submitted = $tenant->timesheet($author, TimesheetStatus::SUBMITTED);

    $response = $this->actingAs($reviewer)
        ->getJson($tenant->projectUrl('timesheets'))
        ->assertOk();

    $timesheetIds = collect($response->json('data'))->pluck('id');

    expect($timesheetIds)->toContain($submitted->id)
        ->not->toContain($draft->id);
});

it('returns a domain conflict for an exact duplicate period', function () {
    $tenant = TenantFixture::create();
    $author = $tenant->employee();
    $tenant->membership($author);
    $timesheet = $tenant->timesheet($author);

    $this->actingAs($author)
        ->postJson($tenant->projectUrl('timesheets'), [
            'period_start' => $timesheet->period_start->toDateString(),
            'period_end'   => $timesheet->period_end->toDateString(),
        ])->assertConflict()
        ->assertJsonPath('data.errorCode', 'duplicate_timesheet_period');

    expect($author->timesheets()->count())->toBe(1);
});

it('updates partial periods and reports inverted boundaries on the changed field', function () {
    $tenant = TenantFixture::create();
    $author = $tenant->employee();
    $tenant->membership($author);
    $timesheet   = $tenant->timesheet($author);
    $url         = $tenant->projectUrl('timesheets/'.$timesheet->id);
    $extendedEnd = $timesheet->period_end->addDay();

    $this->actingAs($author)
        ->patchJson($url, [
            'period_end' => $extendedEnd->toDateString(),
        ])->assertOk();

    expect($timesheet->refresh()->period_start->toDateString())
        ->toBe(today()->startOfWeek()->toDateString())
        ->and($timesheet->period_end->toDateString())
        ->toBe($extendedEnd->toDateString());

    $this->patchJson($url, [
        'period_start' => $extendedEnd->addDay()->toDateString(),
    ])->assertUnprocessable()
        ->assertJsonPath('data.errorCode', 'invalid_timesheet_period')
        ->assertJsonPath(
            'data.errors.period_start.0',
            'The period start must not be after the period end.',
        );
});

it('requires and persists a rejection comment', function () {
    $tenant   = TenantFixture::create();
    $author   = $tenant->employee();
    $reviewer = $tenant->employee();
    $tenant->membership($author, ProjectRole::PARTICIPANT);
    $tenant->membership($reviewer, ProjectRole::SENIOR);
    $timesheet = $tenant->timesheet($author, TimesheetStatus::SUBMITTED);
    $url       = $tenant->projectUrl('timesheets/'.$timesheet->id.'/reject');

    $this->actingAs($reviewer)
        ->postJson($url)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('review_comment');

    $this->postJson($url, [
        'review_comment' => 'Please correct Tuesday.',
    ])->assertOk()
        ->assertJsonPath('data.status', TimesheetStatus::REJECTED->value)
        ->assertJsonPath('data.reviewComment', 'Please correct Tuesday.')
        ->assertJsonPath('data.reviewedBy.id', $reviewer->id);

    $timesheet->refresh();

    expect($timesheet->review_comment)->toBe('Please correct Tuesday.')
        ->and($timesheet->reviewed_by_user_id)->toBe($reviewer->id)
        ->and($timesheet->reviewed_at)->not->toBeNull();
});

it('allows approval without an optional comment', function () {
    $tenant = TenantFixture::create();
    $author = $tenant->employee();
    $tenant->membership($author);
    $timesheet = $tenant->timesheet($author, TimesheetStatus::SUBMITTED);

    $this->actingAs($tenant->admin)
        ->postJson($tenant->projectUrl('timesheets/'.$timesheet->id.'/approve'))
        ->assertOk()
        ->assertJsonPath('data.status', TimesheetStatus::APPROVED->value)
        ->assertJsonMissingPath('data.reviewComment');

    expect($timesheet->refresh()->review_comment)->toBeNull();
});
