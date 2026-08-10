<?php

declare(strict_types=1);

use App\Enums\TimesheetStatus;
use App\Models\TimeEntry;
use Tests\Support\TenantFixture;

it('accepts zero hours and rejects dates outside the timesheet period', function () {
    $tenant = TenantFixture::create();
    $author = $tenant->employee();
    $tenant->membership($author);
    $timesheet  = $tenant->timesheet($author);
    $entriesUrl = $tenant->projectUrl('timesheets/'.$timesheet->id.'/entries');

    $this->actingAs($author)
        ->postJson($entriesUrl, [
            'work_date'   => $timesheet->period_start->toDateString(),
            'description' => null,
            'hours'       => 0,
        ])->assertCreated()
        ->assertJsonPath('data.hours', '0.00')
        ->assertJsonMissingPath('data.description');

    $this->postJson($entriesUrl, [
        'work_date' => $timesheet->period_end->addDay()->toDateString(),
        'hours'     => 8,
    ])->assertUnprocessable()
        ->assertJsonPath('data.errorCode', 'time_entry_outside_timesheet_period')
        ->assertJsonPath('data.errors.work_date.0', 'The work date must be within the timesheet period.');
});

it('returns the freshly updated entry and supports nullable descriptions', function () {
    $tenant = TenantFixture::create();
    $author = $tenant->employee();
    $tenant->membership($author);
    $timesheet = $tenant->timesheet($author);
    $entry     = TimeEntry::factory()->for($timesheet)->create([
        'description' => 'Before',
        'hours'       => 1,
    ]);
    $url = $tenant->projectUrl('timesheets/'.$timesheet->id.'/entries/'.$entry->id);

    $this->actingAs($author)
        ->patchJson($url, [
            'description' => null,
            'hours'       => 3.5,
            'work_date'   => $timesheet->period_start->addDay()->toDateString(),
            'is_overtime' => true,
        ])->assertOk()
        ->assertJsonPath('data.hours', '3.50')
        ->assertJsonMissingPath('data.description')
        ->assertJsonPath('data.isOvertime', true);

    expect($entry->refresh()->description)->toBeNull()
        ->and($entry->hours)->toBe('3.50')
        ->and($entry->work_date->toDateString())->toBe($timesheet->period_start->addDay()->toDateString())
        ->and($entry->is_overtime)->toBeTrue();

    $this->deleteJson($url)->assertNoContent();
    $this->assertDatabaseMissing('time_entries', ['id' => $entry->id]);
});

it('allows edits for rejected timesheets but not submitted ones', function () {
    $tenant = TenantFixture::create();
    $author = $tenant->employee();
    $tenant->membership($author);
    $rejected  = $tenant->timesheet($author, TimesheetStatus::REJECTED);
    $submitted = $tenant->timesheet($author, TimesheetStatus::SUBMITTED);

    $this->actingAs($author)
        ->postJson($tenant->projectUrl('timesheets/'.$rejected->id.'/entries'), [
            'work_date' => $rejected->period_start->toDateString(),
            'hours'     => 2,
        ])->assertCreated();

    $this->postJson($tenant->projectUrl('timesheets/'.$submitted->id.'/entries'), [
        'work_date' => $submitted->period_start->toDateString(),
        'hours'     => 2,
    ])->assertForbidden();
});

it('does not resolve an entry through another timesheet', function () {
    $tenant = TenantFixture::create();
    $author = $tenant->employee();
    $tenant->membership($author);
    $timesheet      = $tenant->timesheet($author);
    $otherTimesheet = $tenant->timesheet($author);
    $foreignEntry   = TimeEntry::factory()->for($otherTimesheet)->create();

    $this->actingAs($author)
        ->patchJson(
            $tenant->projectUrl('timesheets/'.$timesheet->id.'/entries/'.$foreignEntry->id),
            ['hours' => 4],
        )->assertNotFound();
});
