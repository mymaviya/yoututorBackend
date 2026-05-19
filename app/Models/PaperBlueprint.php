<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaperBlueprint extends Model
{
    protected $fillable = [
        'grade_id',
        'subject_id',
        'exam_name_id',
        'title',
        'total_marks',
        'total_questions',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function grade()
    {
        return $this->belongsTo(Grade::class);
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

    public function bloomLevels()
    {
        return $this->hasMany(PaperBlueprintBloomLevel::class,'paper_blueprint_id');
    }
}
