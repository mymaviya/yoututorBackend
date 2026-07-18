<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableRule extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'subscription_id',
        'academic_year_id',
        'rule_key',
        'rule_value',
        'value_type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function getTypedValueAttribute()
    {
        return match ($this->value_type) {
            'boolean' => filter_var($this->rule_value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->rule_value,
            'decimal' => (float) $this->rule_value,
            'json' => json_decode($this->rule_value, true),
            default => $this->rule_value,
        };
    }
}