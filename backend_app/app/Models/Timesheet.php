<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TimesheetStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TimesheetFactory;
use DomainException;
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
        //admin can view timesheets only for owned organizations
        if ($user->isAdmin()) {
            return $query->whereHas(
                'workspace.organization',
                fn(Builder $builder): Builder => $builder->where('owner_id', $user->getKey())
            );
        }

        $viewerMembership = $project->memberships()
            ->whereBelongsTo($user)
            ->where('active', true)
            ->first(['approval_rank']);

        return $query->where(function (Builder $query) use ($user, $project, $viewerMembership): void {
            // author can view all his timesheets
            $query->whereBelongsTo($user, 'user');

            //if user dont have membership - return
            if ($viewerMembership === null) {
                return;
            }

            //approver only sees timesheets with 'submitted' status,
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
            throw new DomainException('The period start must not be after the period end.');
        }

        $isActiveMember = $project->memberships()
            ->whereBelongsTo($user)
            ->where('active', true)
            ->exists();

        if (!$isActiveMember) {
            throw new DomainException('The user must be an active project member.');
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
     * @phpstan-return Model
     *
     * @throws Throwable
     */
    public function addEntry(array $attributes): TimeEntry
    {
        $workDate = CarbonImmutable::parse($attributes['work_date']);

        if (!$workDate->betweenIncluded($this->period_start, $this->period_end)) {
            throw new DomainException('The work date must be within the timesheet period.');
        }

        if (!in_array($this->status, [TimesheetStatus::DRAFT, TimesheetStatus::REJECTED], true)) {
            throw new DomainException('Entries may only be changed on draft or rejected timesheets.');
        }

        return DB::transaction(function () use ($attributes) {
            $entry = $this->entries()->make($attributes);

            $entry->forceFill([
                'timesheet_id' => $this->id,
            ])->saveOrFail();

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
    public function updateEntry(TimeEntry $entry, array $attributes): void
    {
        $workDate = array_key_exists('work_date', $attributes)
            ? CarbonImmutable::parse($attributes['work_date'])
            : CarbonImmutable::parse($entry->work_date);

        if (!$workDate->betweenIncluded($this->period_start, $this->period_end)) {
            throw new DomainException('The work date must be within the timesheet period.');
        }

        if (!in_array($this->status, [TimesheetStatus::DRAFT, TimesheetStatus::REJECTED], true)) {
            throw new DomainException('Entries may only be changed on draft or rejected timesheets.');
        }

        $entry->updateOrFail($attributes);
    }

    /**
     * @throws Throwable
     */
    public function removeEntry(TimeEntry $entry): void
    {
        if (!in_array($this->status, [TimesheetStatus::DRAFT, TimesheetStatus::REJECTED], true)) {
            throw new DomainException('Entries may only be changed on draft or rejected timesheets.');
        }

        $entry->deleteOrFail();
    }

    /**
     * @throws Throwable
     */
    public function submit(): self
    {
        return DB::transaction(function () {
            $timesheet = $this->query()
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($timesheet->status, [TimesheetStatus::DRAFT, TimesheetStatus::REJECTED], true)) {
                throw new DomainException('Entries may only be changed on draft or rejected timesheets.');
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
            $timesheet = $this->query()
                ->lockForUpdate()
                ->firstOrFail();

            if ($timesheet->status !== TimesheetStatus::SUBMITTED) {
                throw new DomainException('Entries may only be changed on draft or rejected timesheets.');
            }

            if (!in_array($decision, [TimesheetStatus::APPROVED, TimesheetStatus::REJECTED], true)) {
                throw new DomainException("Decision must be 'approved' or 'rejected'.");
            }

            $timesheet->forceFill([
                'reviewed_by_user_id' => $reviewer->getKey(),
                'reviewed_at' => Carbon::now(),
                'review_comment' => $reviewComment,
                'status' => $decision
            ])->saveOrFail();

            return $timesheet;
        });
    }

    public
    function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public
    function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public
    function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public
    function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public
    function entries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
