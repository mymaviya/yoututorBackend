<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionTypeTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(QuestionTypeTemplateItem::class);
    }

    public function questionTypes()
    {
        return $this->belongsToMany(
            QuestionTypeMaster::class,
            'question_type_template_items',
            'question_type_template_id',
            'question_type_master_id'
        );
    }
}