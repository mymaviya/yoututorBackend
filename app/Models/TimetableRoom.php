<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetableRoom extends Model
{
    use BelongsToSubscription;

    public const TYPES = [
        'classroom',
        'laboratory',
        'computer_lab',
        'library',
        'activity',
        'other',
    ];

    protected $fillable = [
        'subscription_id',
        'name',
        'code',
        'room_type',
        'capacity',
        'supported_subject_ids',
        'is_active',
    ];

    protected $casts = [
        'id' => 'integer',
        'subscription_id' => 'integer',
        'capacity' => 'integer',
        'supported_subject_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class, 'room_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('room_type', $type);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('room_type')->orderBy('name')->orderBy('id');
    }

    public function supportsSubject(?int $subjectId): bool
    {
        $subjectIds = collect($this->supported_subject_ids ?? [])->map(fn ($id) => (int) $id);

        return $subjectIds->isEmpty()
            || ($subjectId !== null && $subjectIds->contains($subjectId));
    }
}