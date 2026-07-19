<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParallelGroupItem extends Model
{
    protected $fillable = [
        'parallel_group_id',
        'subject_id',
        'teacher_id',
        'stream_ids',
        'student_group_name',
        'teacher_split_order',
        'room_no',
        'is_active',
    ];

    protected $casts = [
        'id' => 'integer',
        'parallel_group_id' => 'integer',
        'subject_id' => 'integer',
        'teacher_id' => 'integer',
        'stream_ids' => 'array',
        'teacher_split_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function parallelGroup(): BelongsTo
    {
        return $this->belongsTo(
            ParallelGroup::class,
            'parallel_group_id'
        );
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(
            Subject::class,
            'subject_id'
        );
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'teacher_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForGroup(
        Builder $query,
        int $parallelGroupId
    ): Builder {
        return $query->where(
            'parallel_group_id',
            $parallelGroupId
        );
    }

    public function scopeForTeacher(
        Builder $query,
        int $teacherId
    ): Builder {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForSubject(
        Builder $query,
        int $subjectId
    ): Builder {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeForStream(
        Builder $query,
        int $streamId
    ): Builder {
        return $query->whereJsonContains(
            'stream_ids',
            $streamId
        );
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('teacher_split_order')
            ->orderBy('id');
    }

    public function appliesToStream(?int $streamId): bool
    {
        $streamIds = collect($this->stream_ids ?? [])
            ->map(fn ($id) => (int) $id);

        if ($streamIds->isEmpty()) {
            return true;
        }

        return $streamId !== null
            && $streamIds->contains($streamId);
    }

    public function hasTeacher(): bool
    {
        return $this->teacher_id !== null;
    }

    public function displayGroupName(): string
    {
        return filled($this->student_group_name)
            ? $this->student_group_name
            : 'All Students';
    }
}