<?php

declare(strict_types=1);

use App\Enums\ProjectRole;
use App\Enums\TimesheetStatus;
use App\Events\TimesheetReviewed;
use App\Events\TimesheetSubmitted;
use App\Listeners\NotifyTimesheetApprovers;
use App\Listeners\NotifyTimesheetAuthor;
use App\Models\Project;
use App\Models\Timesheet;
use App\Notifications\TimesheetReviewedNotification;
use App\Notifications\TimesheetSubmittedNotification;
use App\Queries\EloquentFindTimesheetApprovers;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Tests\Support\TenantFixture;

afterEach(function (): void {
    Mockery::close();
});

it('finds only users who can currently review the timesheet', function (): void {
    $tenant                    = TenantFixture::create();
    $author                    = $tenant->employee();
    $higherRank                = $tenant->employee();
    $equalRank                 = $tenant->employee();
    $lowerRank                 = $tenant->employee();
    $inactiveHigherRank        = $tenant->employee();
    $sameWorkspaceOtherProject = $tenant->employee();
    $otherTenant               = TenantFixture::create();
    $otherTenantHigherRank     = $otherTenant->employee();

    $tenant->membership($author, ProjectRole::SENIOR);
    $tenant->membership($higherRank, ProjectRole::MANAGER);
    $tenant->membership($equalRank, ProjectRole::SENIOR);
    $tenant->membership($lowerRank, ProjectRole::PARTICIPANT);
    $tenant->membership($inactiveHigherRank, ProjectRole::PROJECT_LEAD, active: false);
    $tenant->membership($tenant->admin, ProjectRole::PROJECT_LEAD);
    $tenant->membership($otherTenant->admin, ProjectRole::PROJECT_LEAD);
    $otherTenant->membership($otherTenantHigherRank, ProjectRole::PROJECT_LEAD);

    $otherProject = Project::factory()->for($tenant->workspace)->create();
    $tenant->membership(
        $sameWorkspaceOtherProject,
        ProjectRole::PROJECT_LEAD,
        project: $otherProject,
    );

    $recipientIds = app(EloquentFindTimesheetApprovers::class)
        ->find((int) $author->getKey(), (int) $tenant->project->getKey())
        ->modelKeys();

    expect($recipientIds)
        ->toHaveCount(2)
        ->toContain($higherRank->getKey(), $tenant->admin->getKey())
        ->not->toContain(
            $author->getKey(),
            $equalRank->getKey(),
            $lowerRank->getKey(),
            $inactiveHigherRank->getKey(),
            $sameWorkspaceOtherProject->getKey(),
            $otherTenant->admin->getKey(),
            $otherTenantHigherRank->getKey(),
        );
});

it('returns only the owner administrator when the author has no active membership', function (): void {
    $tenant     = TenantFixture::create();
    $author     = $tenant->employee();
    $higherRank = $tenant->employee();

    $tenant->membership($author, ProjectRole::PARTICIPANT, active: false);
    $tenant->membership($higherRank, ProjectRole::PROJECT_LEAD);

    $recipientIds = app(EloquentFindTimesheetApprovers::class)
        ->find((int) $author->getKey(), (int) $tenant->project->getKey())
        ->modelKeys();

    expect($recipientIds)->toBe([$tenant->admin->getKey()]);
});

it('returns no approvers for a missing project', function (): void {
    $tenant = TenantFixture::create();
    $author = $tenant->employee();

    expect(app(EloquentFindTimesheetApprovers::class)->find(
        (int) $author->getKey(),
        999_999,
    ))->toBeEmpty();
});

it('notifies active higher-rank members and the owner administrator on current submit', function (): void {
    $tenant             = TenantFixture::create();
    $author             = $tenant->employee();
    $higherRank         = $tenant->employee();
    $equalRank          = $tenant->employee();
    $inactiveHigherRank = $tenant->employee();

    $tenant->membership($author, ProjectRole::PARTICIPANT);
    $tenant->membership($higherRank, ProjectRole::SENIOR);
    $tenant->membership($equalRank, ProjectRole::PARTICIPANT);
    $tenant->membership($inactiveHigherRank, ProjectRole::PROJECT_LEAD, active: false);

    $timesheet = Timesheet::factory()
        ->for($tenant->project)
        ->for($author, 'user')
        ->submitted()
        ->create(['workspace_id' => $tenant->workspace->getKey()]);

    Notification::fake();

    $event = submittedEvent($timesheet);
    timesheetApproverListener()->handle($event);

    $expectedPayload = [
        'timesheetId' => $timesheet->getKey(),
        'workspaceId' => $tenant->workspace->getKey(),
        'projectId'   => $tenant->project->getKey(),
        'authorId'    => $author->getKey(),
        'submittedAt' => $event->submittedAt,
    ];

    foreach ([$higherRank, $tenant->admin] as $recipient) {
        Notification::assertSentTo(
            $recipient,
            TimesheetSubmittedNotification::class,
            fn (TimesheetSubmittedNotification $notification, array $channels): bool => $channels === ['database']
                && $notification->databaseType($recipient)                                        === 'timesheet.submitted'
                && $notification->toDatabase($recipient)                                          === $expectedPayload,
        );
        Notification::assertSentToTimes($recipient, TimesheetSubmittedNotification::class);
    }

    foreach ([$author, $equalRank, $inactiveHigherRank] as $excludedUser) {
        Notification::assertNotSentTo($excludedUser, TimesheetSubmittedNotification::class);
    }
});

it('skips a submit notification when the timesheet has already changed', function (): void {
    $tenant   = TenantFixture::create();
    $author   = $tenant->employee();
    $approver = $tenant->employee();
    $tenant->membership($author, ProjectRole::PARTICIPANT);
    $tenant->membership($approver, ProjectRole::SENIOR);

    $timesheet = Timesheet::factory()
        ->for($tenant->project)
        ->for($author, 'user')
        ->submitted()
        ->create(['workspace_id' => $tenant->workspace->getKey()]);
    $event = submittedEvent($timesheet);
    $timesheet->forceFill(['status' => TimesheetStatus::APPROVED])->saveOrFail();

    Notification::fake();
    timesheetApproverListener()->handle($event);

    Notification::assertNothingSent();
});

it('notifies only the author using the review event snapshot', function (TimesheetStatus $decision): void {
    $tenant     = TenantFixture::create();
    $author     = $tenant->employee();
    $otherUser  = $tenant->employee();
    $reviewedAt = '2026-08-07 14:15:16';
    $comment    = $decision === TimesheetStatus::REJECTED ? 'Please correct Friday.' : null;
    $event      = new TimesheetReviewed(
        timesheetId: 41,
        workspaceId: (int) $tenant->workspace->getKey(),
        projectId: (int) $tenant->project->getKey(),
        authorId: (int) $author->getKey(),
        reviewerId: (int) $tenant->admin->getKey(),
        decision: $decision->value,
        reviewedAt: $reviewedAt,
        reviewComment: $comment,
    );

    Notification::fake();
    reviewAuthorListener()->handle($event);

    Notification::assertSentTo(
        $author,
        TimesheetReviewedNotification::class,
        fn (TimesheetReviewedNotification $notification, array $channels): bool => $channels === ['database']
            && $notification->databaseType($author)                                          === 'timesheet.reviewed'
            && $notification->toDatabase($author)                                            === [
                'timesheetId'   => 41,
                'workspaceId'   => $tenant->workspace->getKey(),
                'projectId'     => $tenant->project->getKey(),
                'reviewerId'    => $tenant->admin->getKey(),
                'decision'      => $decision->value,
                'reviewedAt'    => $reviewedAt,
                'reviewComment' => $comment,
            ],
    );
    Notification::assertNotSentTo($otherUser, TimesheetReviewedNotification::class);
})->with([
    'approved' => TimesheetStatus::APPROVED,
    'rejected' => TimesheetStatus::REJECTED,
]);

it('defines retry and uniqueness metadata without overriding the test queue connection', function (): void {
    $submittedListener = timesheetApproverListener();
    $reviewedListener  = reviewAuthorListener();
    $firstSubmit       = new TimesheetSubmitted(7, 3, 5, 11, '2026-08-07 10:00:00');
    $secondSubmit      = new TimesheetSubmitted(7, 3, 5, 11, '2026-08-07 11:00:00');
    $review            = new TimesheetReviewed(7, 3, 5, 11, 13, 'approved', '2026-08-07 12:00:00', null);

    expect($submittedListener)
        ->toBeInstanceOf(ShouldQueue::class)
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and($reviewedListener)
        ->toBeInstanceOf(ShouldQueue::class)
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and(property_exists($submittedListener, 'connection'))->toBeFalse()
        ->and(property_exists($reviewedListener, 'connection'))->toBeFalse()
        ->and($submittedListener->queue)->toBe('notifications')
        ->and($reviewedListener->queue)->toBe('notifications')
        ->and($submittedListener->tries)->toBe(3)
        ->and($submittedListener->timeout)->toBe(30)
        ->and($submittedListener->uniqueFor)->toBe(600)
        ->and($submittedListener->backoff($firstSubmit))->toBe([5, 15, 60])
        ->and($reviewedListener->backoff($review))->toBe([5, 15, 60])
        ->and($submittedListener->uniqueId($firstSubmit))
        ->not->toBe($submittedListener->uniqueId($secondSubmit));
});

function submittedEvent(Timesheet $timesheet): TimesheetSubmitted
{
    return new TimesheetSubmitted(
        timesheetId: (int) $timesheet->getKey(),
        workspaceId: (int) $timesheet->workspace_id,
        projectId: (int) $timesheet->project_id,
        authorId: (int) $timesheet->user_id,
        submittedAt: $timesheet->submitted_at->toDateTimeString(),
    );
}

function timesheetApproverListener(): NotifyTimesheetApprovers
{
    return new NotifyTimesheetApprovers(
        app(EloquentFindTimesheetApprovers::class),
        Mockery::mock(LoggerInterface::class, fn (MockInterface $mock) => $mock->shouldIgnoreMissing()),
    );
}

function reviewAuthorListener(): NotifyTimesheetAuthor
{
    return new NotifyTimesheetAuthor(
        Mockery::mock(LoggerInterface::class, fn (MockInterface $mock) => $mock->shouldIgnoreMissing()),
    );
}
