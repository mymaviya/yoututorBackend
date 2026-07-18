<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Grade extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

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

    protected static function booted(): void
    {
        static::addGlobalScope('sortByName', function (Builder $query): void {
            if (DB::connection()->getDriverName() === 'mysql') {
                $query
                    ->orderByRaw(
                        "CASE WHEN REGEXP_SUBSTR(name, '[0-9]+') IS NULL THEN 1 ELSE 0 END"
                    )
                    ->orderByRaw(
                        "CAST(REGEXP_SUBSTR(name, '[0-9]+') AS UNSIGNED)"
                    )
                    ->orderBy('name');

                return;
            }

            // SQLite and other database engines used by automated tests do not
            // provide MySQL's REGEXP_SUBSTR function.
            $query->orderBy('name');
        });
    }
}