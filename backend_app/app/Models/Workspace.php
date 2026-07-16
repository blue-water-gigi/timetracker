<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'active',
    ];

    protected $hidden = [
        'join_code_hash',
    ];

    protected $attributes = [
        'active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public static function generateJoinCode(): string
    {
        return Str::random(64);
    }

    public static function hashJoinCode(string $joinCode): string
    {
        return hash('sha256', $joinCode);
    }

    #[Scope]
    protected function whereJoinCode(Builder $query, string $joinCode): Builder
    {
        return $query->where('join_code_hash', self::hashJoinCode($joinCode));
    }

    public function rotateJoinCode(): string
    {
        $joinCode = self::generateJoinCode();

        $this->forceFill([
            'join_code_hash' => self::hashJoinCode($joinCode),
        ])->save();

        return $joinCode;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }
}
