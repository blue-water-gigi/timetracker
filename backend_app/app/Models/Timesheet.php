<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TimesheetStatus;
use Database\Factories\TimesheetFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property TimesheetStatus $status
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
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
                fn (Builder $builder): Builder => $builder->where('owner_id', $user->getKey())
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
                        fn (Builder $builder) => $builder->whereBelongsTo($project)
                            ->where('active', true)
                            ->where('approval_rank', '<', $viewerMembership->approval_rank->value)
                    );
            });
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
