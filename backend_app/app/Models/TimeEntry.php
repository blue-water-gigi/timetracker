<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'work_date',
        'description',
        'hours',
        'is_overtime',
    ];

    protected $attributes = [
        'is_overtime' => false,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'hours' => 'decimal:2',
            'is_overtime' => 'boolean',
        ];
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }
}
