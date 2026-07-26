<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'active',
        'period_start',
        'period_end',
    ];

    protected $attributes = [
        'active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user, Workspace $workspace): Builder
    {
        $query->where('workspace_id', $workspace->getKey());

        // admin can view projects only for owned workspaces and organizations
        if ($user->isAdmin()) {
            return $query->whereHas(
                'workspace.organization',
                fn (Builder $builder): Builder => $builder->where('owner_id', $user->getKey())
            );
        }

        // employee can see projects if he belongs to active project as active member
        return $query->where($query->qualifyColumn('active'), true)
            ->whereHas(
                'memberships',
                fn (Builder $builder): Builder => $builder
                    ->whereBelongsTo($user)
                    ->where($builder->qualifyColumn('active'), true)
            );
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->using(ProjectMember::class)
            ->withPivot('project_role', 'approval_rank', 'active')
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }
}
