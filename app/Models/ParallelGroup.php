<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParallelGroup extends Model
{
    protected $fillable = [
        'grade_id',
        'name',
        'same_period_required',
        'period_number_fixed',
        'preferred_period_number',
        'weekly_periods',
        'prefer_morning',
        'prefer_last_period',
        'prefer_saturday',
        'is_active',
    ];

    protected $casts = [
        'same_period_required' => 'boolean',
        'period_number_fixed' => 'boolean',
        'preferred_period_number' => 'integer',
        'weekly_periods' => 'integer',
        'prefer_morning' => 'boolean',
        'prefer_last_period' => 'boolean',
        'prefer_saturday' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ParallelGroupItem::class);
    }
}