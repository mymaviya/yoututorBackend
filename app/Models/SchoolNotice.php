<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolNotice extends Model
{
    protected $fillable = [
        'title',
        'description',
        'icon',
        'start_date',
        'end_date',
        'is_active',
        'show_on_dashboard',
        'show_on_website',
        'show_to_students',
        'show_to_teachers',
        'show_to_parents',
        'priority',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'show_on_dashboard' => 'boolean',
        'show_on_website' => 'boolean',
        'show_to_students' => 'boolean',
        'show_to_teachers' => 'boolean',
        'show_to_parents' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
}