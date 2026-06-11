<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }

    public function streams()
    {
        return $this->belongsToMany(Stream::class, 'subjects')->distinct();
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }

    protected static function booted()
    {
        static::addGlobalScope('sortByName', function ($query) {
            $query->orderByRaw("CAST(REGEXP_SUBSTR(name, '[0-9]+') AS UNSIGNED)");
        });
    }
}
