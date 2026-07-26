<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Throwable;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<Workspace, $this> */
    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    /** @return HasManyThrough<User, Workspace, $this> */
    public function users(): HasManyThrough
    {
        return $this->hasManyThrough(
            User::class,
            Workspace::class,
            'organization_id',
            'workspace_id',
        );
    }

    /** @throws Throwable */
    public function archive(): void
    {
        DB::transaction(function (): void {
            foreach ($this->workspaces()->get() as $workspace) {
                $workspace->archive();
            }

            $this->deleteOrFail();
        });
    }
}
