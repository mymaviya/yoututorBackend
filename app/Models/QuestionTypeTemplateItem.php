<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionTypeTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_type_template_id',
        'question_type_master_id',
    ];

    public function template()
    {
        return $this->belongsTo(QuestionTypeTemplate::class, 'question_type_template_id');
    }

    public function questionType()
    {
        return $this->belongsTo(QuestionTypeMaster::class, 'question_type_master_id');
    }
}