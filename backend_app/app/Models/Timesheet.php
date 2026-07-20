<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TimesheetStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TimesheetFactory;
use DomainException;
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

    /**
     * @param array{period_start: string, period_end: string} $attributes
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

        $entry = DB::transaction(function () use ($attributes) {
            $entry = $this->entries()->make($attributes);

            $entry->forceFill([
                'timesheet_id' => $this->id
            ])->saveOrFail();

            return $entry;
        });

        assert($entry instanceof TimeEntry);

        return $entry;
    }

    /**
     * @throws Throwable
     */
    public function updateEntry(TimeEntry $entry, array $attributes): void
    {
        $workDate = CarbonImmutable::parse($attributes['work_date']);

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
