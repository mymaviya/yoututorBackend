<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionPaper extends Model
{
    protected $fillable = [
        'title',
        'exam_type',
        'duration',
        'instructions',
        'grade_id',
        'subject_id',
        'paper_blueprint_id',
        'is_active',
        'total_marks',
        'created_by',
        'status',
        'finalized_at',
        'finalized_by',
        'printed_at',
        'printed_by',
        'archived_at',
        'archived_by',
    ];

    public function grade()
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function questions()
    {
        return $this->hasMany(QuestionPaperQuestion::class)->orderBy('sort_order');
    }

    public function items()
    {
        return $this->hasMany(QuestionPaperItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function printedBy()
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function blueprint()
    {
        return $this->belongsTo(PaperBlueprint::class, 'paper_blueprint_id');
    }
}
