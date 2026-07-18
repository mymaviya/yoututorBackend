<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class TeacherAssignment extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'subscription_id',
        'teacher_id',
        'grade_id',
        'section_id',
        'stream_id',
        'subject_id',

        'is_primary_teacher',
        'priority',
        'max_periods_per_week',

        'effective_from',
        'effective_to',

        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_primary_teacher' => 'boolean',
        'priority' => 'integer',
        'max_periods_per_week' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function stream()
    {
        return $this->belongsTo(Stream::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
