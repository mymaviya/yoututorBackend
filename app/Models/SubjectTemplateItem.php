<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectTemplateItem extends Model
{
    protected $fillable = [
        'subject_template_id',
        'subject_name',
    ];

    public function template()
    {
        return $this->belongsTo(SubjectTemplate::class, 'subject_template_id');
    }
}
