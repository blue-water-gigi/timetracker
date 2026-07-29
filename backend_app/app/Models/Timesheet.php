<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TimesheetStatus;
use App\Exceptions\Domain\InvalidTimesheetPeriodException;
use App\Exceptions\Domain\NotValidStatusDecisionException;
use App\Exceptions\Domain\ProjectMembershipRequiredException;
use App\Exceptions\Domain\TimeEntryOutsideTimesheetPeriodException;
use App\Exceptions\Domain\TimesheetAlreadyProcessedException;
use App\Exceptions\Domain\TimesheetNotSubmittedException;
use App\Exceptions\Domain\TimesheetPeriodContainsEntriesException;
use Carbon\CarbonImmutable;
use Database\Factories\TimesheetFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * @property TimesheetStatus $status
 * @property Carbon $period_start
 * @property Carbon $period_end
 */
class Timesheet extends Model
{
    /** @use HasFactory<TimesheetFactory> */
    use HasFactory;

    protected $fillable = [
        'period_start',
        'period_end',
        'review_comment',
    ];

    protected $attributes = [
        'status' => TimesheetStatus::DRAFT->value,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => TimesheetStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user, Project $project): Builder
    {
        // admin can view timesheets only for owned organizations
        if ($user->isAdmin()) {
            return $query->whereHas(
                'workspace.organization',
                fn(Builder $builder): Builder => $builder->where('owner_id', $user->getKey())
            );
        }

        $viewerMembership = ProjectMember::query()
            ->whereBelongsTo($project)
            ->whereBelongsTo($user)
            ->where('active', true)
            ->first(['approval_rank']);

        return $query->where(function (Builder $query) use ($user, $project, $viewerMembership): void {
            // author can view all his timesheets
            $query->whereBelongsTo($user, 'user');

            // if user dont have membership - return
            if ($viewerMembership === null) {
                return;
            }

            // approver only sees timesheets with 'submitted' status,
            // also if author approval rank < then approver
            // also if user is active
            $query->orWhere(function (Builder $query) use ($viewerMembership, $project): void {
                $query->where('status', TimesheetStatus::SUBMITTED)
                    ->whereHas(
                        'user.projectMemberships',
                        fn(Builder $builder) => $builder->whereBelongsTo($project)
                            ->where('active', true)
                            ->where('approval_rank', '<', $viewerMembership->approval_rank->value)
                    );
            });
        });
    }

    /**
     * @param array{period_start: string, period_end: string} $attributes
     *
     * @throws Throwable
     */
    public static function createForProject(Project $project, User $user, array $attributes): self
    {
        $periodStart = CarbonImmutable::parse($attributes['period_start']);
        $periodEnd = CarbonImmutable::parse($attributes['period_end']);

        if ($periodStart->isAfter($periodEnd)) {
            throw InvalidTimesheetPeriodException::make($periodStart, $periodEnd);
        }

        $isActiveMember = $project->memberships()
            ->whereBelongsTo($user)
            ->where('active', true)
            ->exists();

        if (!$isActiveMember) {
            throw ProjectMembershipRequiredException::make();
        }

        return DB::transaction(function () use ($attributes, $project, $user): self {
            $timesheet = new self($attributes);
            $timesheet->forceFill([
                'workspace_id' => $project->workspace_id,
                'project_id' => $project->id,
                'user_id' => $user->id,
            ]);
            $timesheet->saveOrFail();

            return $timesheet;
        });
    }

    /**
     * @param array{work_date: string, description?: string|null, hours: numeric-string|int|float, is_overtime?: bool} $attributes
     *
     * @throws Throwable
     */
    public function addEntry(array $attributes): TimeEntry
    {
        return DB::transaction(function () use ($attributes): TimeEntry {
            $workDate = CarbonImmutable::parse($attributes['work_date']);

            $timesheet = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (!$workDate->betweenIncluded($timesheet->period_start, $timesheet->period_end)) {
                throw TimeEntryOutsideTimesheetPeriodException::make($timesheet->period_start, $timesheet->period_end);
            }

            $allowedStatuses = [TimesheetStatus::DRAFT, TimesheetStatus::REJECTED];

            if (!in_array($timesheet->status, $allowedStatuses, true)) {
                throw TimesheetAlreadyProcessedException::make($timesheet->status, $allowedStatuses);
            }

            $entry = new TimeEntry($attributes);
            $entry->timesheet()->associate($timesheet);
            $entry->saveOrFail();

            return $entry;
        });
    }

    /**
     * @param array{
     *      work_date?: string,
     *      description?: string|null,
     *      hours?: numeric-string|int|float,
     *      is_overtime?: bool
     *  } $attributes
     *
     * @throws Throwable
     */
    public function updateEntry(TimeEntry $entry, array $attributes): TimeEntry
    {
        return DB::transaction(function () use ($entry, $attributes): TimeEntry {
            $timesheet = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $timeEntry = TimeEntry::query()
                ->whereBelongsTo($timesheet)
                ->whereKey($entry->getKey())
                ->firstOrFail();

            $workDate = array_key_exists('work_date', $attributes)
                ? CarbonImmutable::parse($attributes['work_date'])
                : CarbonImmutable::parse($timeEntry->work_date);

            if (!$workDate->betweenIncluded($timesheet->period_start, $timesheet->period_end)) {
                throw TimeEntryOutsideTimesheetPeriodException::make($timesheet->period_start, $timesheet->period_end);
            }

            $allowedStatuses = [TimesheetStatus::DRAFT, TimesheetStatus::REJECTED];

            if (!in_array($timesheet->status, $allowedStatuses, true)) {
                throw TimesheetAlreadyProcessedException::make($timesheet->status, $allowedStatuses);
            }

            $timeEntry->updateOrFail($attributes);

            return $timeEntry;
        });
    }

    /**
     * @param array{period_start?: string, period_end?: string} $attributes
     *
     * @throws Throwable
     */
    public function updatePeriod(array $attributes): self
    {
        return DB::transaction(function () use ($attributes): self {
            /** @var self $timesheet */
            $timesheet = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $allowedStatuses = [
                TimesheetStatus::DRAFT,
                TimesheetStatus::REJECTED,
            ];

            if (!in_array($timesheet->status, $allowedStatuses, true)) {
                throw TimesheetAlreadyProcessedException::make(
                    $timesheet->status,
                    $allowedStatuses,
                );
            }

            $periodStart = CarbonImmutable::parse(
                $attributes['period_start']
                ?? $timesheet->period_start->toDateString(),
            );

            $periodEnd = CarbonImmutable::parse(
                $attributes['period_end']
                ?? $timesheet->period_end->toDateString(),
            );

            if ($periodStart->isAfter($periodEnd)) {
                throw InvalidTimesheetPeriodException::make(
                    $periodStart,
                    $periodEnd,
                );
            }

            $hasOutOfRangeEntries = $timesheet->entries()
                ->where(function (Builder $query) use ($periodStart, $periodEnd): void {
                    $query
                        ->whereDate('work_date', '<', $periodStart->toDateString())
                        ->orWhereDate('work_date', '>', $periodEnd->toDateString());
                })
                ->exists();

            if ($hasOutOfRangeEntries) {
                throw TimesheetPeriodContainsEntriesException::make(
                    $periodStart,
                    $periodEnd,
                );
            }
            $changes = [];

            if (array_key_exists('period_start', $attributes)) {
                $changes['period_start'] = $periodStart->toDateString();
            }

            if (array_key_exists('period_end', $attributes)) {
                $changes['period_end'] = $periodEnd->toDateString();
            }

            if ($changes !== []) {
                $timesheet->fill($changes)->saveOrFail();
            }

            return $timesheet;
        });
    }

    /**
     * @throws Throwable
     */
    public function removeEntry(TimeEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $timesheet = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $timeEntry = $timesheet->entries()
                ->whereKey($entry->getKey())
                ->firstOrFail();

            $allowedStatuses = [TimesheetStatus::DRAFT, TimesheetStatus::REJECTED];

            if (!in_array($timesheet->status, $allowedStatuses, true)) {
                throw TimesheetAlreadyProcessedException::make($timesheet->status, $allowedStatuses);
            }

            $timeEntry->deleteOrFail();
        });

    }

    /**
     * @throws Throwable
     */
    public function submit(): self
    {
        return DB::transaction(function () {
            $timesheet = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $allowedStatuses = [TimesheetStatus::DRAFT, TimesheetStatus::REJECTED];

            if (!in_array($timesheet->status, $allowedStatuses, true)) {
                throw TimesheetAlreadyProcessedException::make($timesheet->status, $allowedStatuses);
            }

            $timesheet->forceFill([
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'review_comment' => null,
                'status' => TimesheetStatus::SUBMITTED,
                'submitted_at' => Carbon::now(),
            ])->saveOrFail();

            return $timesheet;
        });
    }

    /**
     * @throws Throwable
     */
    public function review(User $reviewer, TimesheetStatus $decision, ?string $reviewComment): self
    {
        return DB::transaction(function () use ($reviewer, $decision, $reviewComment) {
            $timesheet = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($timesheet->status !== TimesheetStatus::SUBMITTED) {
                throw TimesheetNotSubmittedException::make($timesheet->status, [TimesheetStatus::SUBMITTED]);
            }

            $allowedStatuses = [TimesheetStatus::APPROVED, TimesheetStatus::REJECTED];

            if (!in_array($decision, $allowedStatuses, true)) {
                throw NotValidStatusDecisionException::make($decision, $allowedStatuses);
            }

            $timesheet->forceFill([
                'reviewed_by_user_id' => $reviewer->getKey(),
                'reviewed_at' => Carbon::now(),
                'review_comment' => $reviewComment,
                'status' => $decision,
            ])->saveOrFail();

            return $timesheet;
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
