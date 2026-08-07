<?php

declare(strict_types=1);

use App\Enums\ProjectRole;
use App\Enums\TimesheetStatus;
use App\Events\TimesheetReviewed;
use App\Events\TimesheetSubmitted;
use App\Listeners\NotifyTimesheetApprovers;
use App\Models\Timesheet;
use App\Services\Timesheet\Actions\ReviewTimesheet;
use App\Services\Timesheet\Actions\SubmitTimesheet;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TenantFixture;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->artisan('migrate:fresh');
});

it('queues the submit listener only after the surrounding transaction commits', function (): void {
    $tenant = TenantFixture::create();
    $author = $tenant->employee();
    $tenant->membership($author);
    $timesheet = $tenant->timesheet($author);
    Queue::fake();

    DB::beginTransaction();

    app(SubmitTimesheet::class)->handle($timesheet);

    Queue::assertNotPushed(CallQueuedListener::class, queuedApproverListener(...));

    DB::commit();

    Queue::assertPushed(CallQueuedListener::class, queuedApproverListener(...));
})->group('redis');

it('does not queue the submit listener when the surrounding transaction rolls back', function (): void {
    $tenant = TenantFixture::create();
    $author = $tenant->employee();
    $tenant->membership($author);
    $timesheet = $tenant->timesheet($author);
    Queue::fake();

    DB::beginTransaction();

    app(SubmitTimesheet::class)->handle($timesheet);

    DB::rollBack();

    Queue::assertNotPushed(CallQueuedListener::class, queuedApproverListener(...));
});

it('publishes one reviewed event with the committed decision snapshot', function (TimesheetStatus $decision): void {
    $tenant = TenantFixture::create();
    $author = $tenant->employee();
    $reviewer = $tenant->employee();
    $tenant->membership($author, ProjectRole::PARTICIPANT);
    $tenant->membership($reviewer, ProjectRole::SENIOR);
    $comment = $decision === TimesheetStatus::REJECTED ? 'Please correct Friday.' : null;
    $timesheet = Timesheet::factory()
        ->for($tenant->project)
        ->for($author, 'user')
        ->submitted()
        ->create(['workspace_id' => $tenant->workspace->getKey()]);
    Event::fake([TimesheetReviewed::class]);

    app(ReviewTimesheet::class)->handle($reviewer, $timesheet, $decision, $comment);

    Event::assertDispatchedTimes(TimesheetReviewed::class, 1);
    Event::assertDispatched(
        TimesheetReviewed::class,
        fn (TimesheetReviewed $event): bool => $event->timesheetId === $timesheet->getKey()
            && $event->workspaceId === $tenant->workspace->getKey()
            && $event->projectId === $tenant->project->getKey()
            && $event->authorId === $author->getKey()
            && $event->reviewerId === $reviewer->getKey()
            && $event->decision === $decision->value
            && $event->reviewComment === $comment
            && $event->reviewedAt === $timesheet->refresh()->reviewed_at?->toDateTimeString(),
    );
})->with([
    'approved' => TimesheetStatus::APPROVED,
    'rejected' => TimesheetStatus::REJECTED,
]);

it('places and processes the submitted listener through Redis', function (): void {
    $queue = redisStageThreeQueue();
    $queue->clear('notifications');

    try {
        $tenant = TenantFixture::create();
        $author = $tenant->employee();
        $approver = $tenant->employee();
        $tenant->membership($author, ProjectRole::PARTICIPANT);
        $tenant->membership($approver, ProjectRole::SENIOR);
        $timesheet = Timesheet::factory()
            ->for($tenant->project)
            ->for($author, 'user')
            ->submitted()
            ->create(['workspace_id' => $tenant->workspace->getKey()]);

        config()->set('queue.default', 'stage3_testing');

        TimesheetSubmitted::dispatch(
            timesheetId: (int) $timesheet->getKey(),
            workspaceId: (int) $timesheet->workspace_id,
            projectId: (int) $timesheet->project_id,
            authorId: (int) $timesheet->user_id,
            submittedAt: $timesheet->submitted_at->toDateTimeString(),
        );

        expect($queue->size('notifications'))->toBe(1);

        $job = $queue->pop('notifications');

        if ($job === null) {
            throw new RuntimeException('The Redis queue did not return the submitted listener job.');
        }

        $job->fire();
        $job->delete();

        $notification = $approver->notifications()->first();

        expect($queue->size('notifications'))->toBe(0)
            ->and($approver->notifications()->count())->toBe(1)
            ->and($tenant->admin->notifications()->count())->toBe(1)
            ->and($author->notifications()->count())->toBe(0)
            ->and($notification?->type)->toBe('timesheet.submitted')
            ->and($notification?->data)->toMatchArray([
                'timesheetId' => $timesheet->getKey(),
                'workspaceId' => $tenant->workspace->getKey(),
                'projectId' => $tenant->project->getKey(),
                'authorId' => $author->getKey(),
            ]);
    } finally {
        $queue->clear('notifications');
    }
})->group('redis');

function queuedApproverListener(CallQueuedListener $job): bool
{
    return $job->class === NotifyTimesheetApprovers::class;
}

function redisStageThreeQueue(): RedisQueue
{
    config()->set('queue.connections.stage3_testing', [
        'driver' => 'redis',
        'connection' => 'testing',
        'queue' => 'notifications',
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => true,
    ]);

    $queue = Queue::connection('stage3_testing');

    if (! $queue instanceof RedisQueue) {
        throw new RuntimeException('The stage 3 test queue must use the Redis driver.');
    }

    return $queue;
}
