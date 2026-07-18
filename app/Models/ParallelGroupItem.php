<?php

namespace App\Models;

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
        'stream_ids' => 'array',
        'teacher_split_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function parallelGroup(): BelongsTo
    {
        return $this->belongsTo(ParallelGroup::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}