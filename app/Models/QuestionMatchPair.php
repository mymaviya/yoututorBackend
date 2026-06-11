<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionMatchPair extends Model
{
    protected $fillable = [
        'question_id',
        'left_value',
        'right_value',
        'sort_order',
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
