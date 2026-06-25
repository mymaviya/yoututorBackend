<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class PaperBlueprint extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'subscription_id',
        'grade_id',
        'stream_id',
        'subject_id',
        'exam_name_id',
        'name',
        'duration_minutes',
        'total_marks',
        'is_active',
    ];

    protected $casts = [
        'total_marks' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function stream()
    {
        return $this->belongsTo(Stream::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function examName()
    {
        return $this->belongsTo(ExamName::class);
    }

    public function sections()
    {
        return $this->hasMany(PaperBlueprintSection::class)->orderBy('sort_order');
    }
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

}
