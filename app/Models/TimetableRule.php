<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableRule extends Model
{
    use BelongsToSubscription;

    public const VALUE_TYPES = ['string', 'boolean', 'integer', 'decimal', 'json'];
    public const CONSTRAINT_TYPES = ['hard', 'soft'];

    protected $fillable = [
        'subscription_id',
        'academic_year_id',
        'rule_key',
        'rule_value',
        'value_type',
        'constraint_type',
        'priority',
        'description',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'id' => 'integer',
        'subscription_id' => 'integer',
        'academic_year_id' => 'integer',
        'priority' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function getTypedValueAttribute(): mixed
    {
        return match ($this->value_type) {
            'boolean' => filter_var($this->rule_value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->rule_value,
            'decimal' => (float) $this->rule_value,
            'json' => json_decode((string) $this->rule_value, true),
            default => $this->rule_value,
        };
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeHard(Builder $query): Builder
    {
        return $query->where('constraint_type', 'hard');
    }

    public function scopeSoft(Builder $query): Builder
    {
        return $query->where('constraint_type', 'soft');
    }

    public function scopeForAcademicYear(
        Builder $query,
        ?int $academicYearId
    ): Builder {
        return $query->where(function (Builder $scope) use ($academicYearId) {
            $scope->whereNull('academic_year_id');

            if ($academicYearId !== null) {
                $scope->orWhere('academic_year_id', $academicYearId);
            }
        });
    }

    public function scopeEffectiveOn(
        Builder $query,
        string|\DateTimeInterface|null $date = null
    ): Builder {
        $effectiveDate = $date ?? now();

        return $query
            ->where(function (Builder $scope) use ($effectiveDate) {
                $scope->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $effectiveDate);
            })
            ->where(function (Builder $scope) use ($effectiveDate) {
                $scope->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $effectiveDate);
            });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE WHEN constraint_type = 'hard' THEN 0 ELSE 1 END")
            ->orderByDesc('priority')
            ->orderBy('rule_key')
            ->orderBy('id');
    }
}
