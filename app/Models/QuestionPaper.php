<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionPaper extends Model
{
    protected $fillable = [
        'grade_id',
        'stream_id',
        'subject_id',
        'exam_name_id',
        'paper_blueprint_id',
        'title',
        'instructions',
        'total_marks',
        'duration_minutes',
        'status',
        'created_by',
        'finalized_at',
        'finalized_by',
        'printed_at',
        'printed_by',
        'archived_at',
        'archived_by',
    ];

    protected $casts = [
        'total_marks' => 'decimal:2',
        'finalized_at' => 'datetime',
        'printed_at' => 'datetime',
        'archived_at' => 'datetime',
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
    return $this->belongsTo(ExamName::class, 'exam_name_id');
}

    public function blueprint()
    {
        return $this->belongsTo(PaperBlueprint::class, 'paper_blueprint_id');
    }

    public function questions()
    {
        return $this->hasMany(QuestionPaperQuestion::class)->orderBy('sort_order');
    }

    public function items()
    {
        return $this->hasMany(QuestionPaperItem::class)->orderBy('display_order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
