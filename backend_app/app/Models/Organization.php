<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'tin',
        'metadata',
        'archived_at',
    ];

    /**
     * Get the attributes that should be cast.
     * @return array<string, string> 
     */
    public function casts(): array
    {
        return [
            'metadata' => 'json:unicode',
            'archived_at' => 'datetime'
        ];
    }


    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }
}
