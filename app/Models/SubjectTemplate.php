<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectTemplate extends Model
{
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
        return $this->hasMany(SubjectTemplateItem::class);
    }

    public function template()
    {
        return $this->belongsTo(SubjectTemplate::class, 'subject_template_id');
    }
}
