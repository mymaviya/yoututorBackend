<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionPaperItem extends Model
{
    protected $fillable = [
        'question_paper_id',
        'question_id',
        'section',
        'display_order',
        'is_optional',
        'group_no',
    ];

    protected $casts = ['is_optional' => 'boolean'];

    public function paper()
    {
        return $this->belongsTo(QuestionPaper::class, 'question_paper_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
