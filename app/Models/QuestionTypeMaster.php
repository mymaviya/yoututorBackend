<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionTypeMaster extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'has_options',
        'has_answer',
        'has_match_pairs',
        'is_active',
    ];

    protected $casts = [
        'has_options' => 'boolean',
        'has_answer' => 'boolean',
        'has_match_pairs' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function assignments()
    {
        return $this->hasMany(QuestionTypeAssignment::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function blueprintSections()
    {
        return $this->hasMany(PaperBlueprintSection::class);
    }
}
