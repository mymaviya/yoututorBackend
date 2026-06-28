<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterQuestionMatchPair extends Model
{
    protected $fillable = [
        'master_question_id',
        'left_value',
        'right_value',
        'sort_order',
    ];

    public function question()
    {
        return $this->belongsTo(
            MasterQuestion::class,
            'master_question_id'
        );
    }
}