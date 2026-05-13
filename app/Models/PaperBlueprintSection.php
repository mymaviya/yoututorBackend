<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaperBlueprintSection extends Model
{
    protected $fillable = [
        'paper_blueprint_id',
        'section_name',
        'instructions',
        'question_type',
        'difficulty',
        'bloom_level',
        'question_count',
        'marks_per_question',
        'sort_order',
    ];

    public function blueprint()
    {
        return $this->belongsTo(PaperBlueprint::class, 'paper_blueprint_id');
    }
}
