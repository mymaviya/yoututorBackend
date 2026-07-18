<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = [
        'grade_id',
        'stream_id',
        'name',
        'display_name',
        'capacity',
        'class_teacher_id',
        'room_no',
        'is_active',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'class_teacher_id'
        );
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}