<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaperBlueprintSection extends Model
{
    protected $fillable = [
        'paper_blueprint_id',
        'section_name',
        'question_type_master_id',
        'difficulty',
        'question_count',
        'marks_per_question',
        'instructions',
        'sort_order',
    ];

    protected $casts = [
        'question_count' => 'integer',
        'marks_per_question' => 'decimal:2',
    ];

    public function blueprint()
    {
        return $this->belongsTo(PaperBlueprint::class, 'paper_blueprint_id');
    }

    public function questionType()
    {
        return $this->belongsTo(QuestionTypeMaster::class, 'question_type_master_id');
    }

    public function bloomLevels()
    {
        return $this->hasMany(PaperBlueprintBloomLevel::class, 'paper_blueprint_section_id');
    }
}