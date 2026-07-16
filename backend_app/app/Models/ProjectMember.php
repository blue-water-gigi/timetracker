<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalRank;
use App\Enums\ProjectRole;
use Database\Factories\ProjectMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property ProjectRole $project_role
 * @property ApprovalRank $approval_rank
 */
class ProjectMember extends Pivot
{
    /** @use HasFactory<ProjectMemberFactory> */
    use HasFactory;

    public $incrementing = true;

    protected $table = 'project_members';

    protected $fillable = [
        'user_id',
        'project_role',
        'active',
    ];

    protected $attributes = [
        'active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $membership): void {
            $membership->approval_rank = $membership->project_role->approvalRank();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'project_role' => ProjectRole::class,
            'approval_rank' => ApprovalRank::class,
            'active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
