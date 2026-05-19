<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionType extends Model
{
    protected $fillable = [
        'grade_id',
        'subject_id',
        'name',
        'slug',
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

    public function questionType()
    {
        return $this->belongsTo(QuestionType::class, 'type', 'slug');
    }

    protected $appends = ['type_name'];

    public function getTypeNameAttribute()
    {
        return $this->questionType?->name ?? $this->type;
    }

}
