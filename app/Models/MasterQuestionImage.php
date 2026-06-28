<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterQuestionImage extends Model
{
    protected $fillable = [
        'master_question_id',
        'image_path',
    ];

    public function question()
    {
        return $this->belongsTo(
            MasterQuestion::class,
            'master_question_id'
        );
    }
}