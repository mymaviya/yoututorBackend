<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaperBlueprintBloomLevel extends Model
{
    protected $fillable = [
        'paper_blueprint_section_id',
        'bloom_level',
        'percentage',
        'calculated_count',
    ];

    protected $casts = [
        'paper_blueprint_section_id' => 'integer',
        'percentage' => 'decimal:2',
        'calculated_count' => 'integer',
    ];

    public function section()
    {
        return $this->belongsTo(PaperBlueprintSection::class, 'paper_blueprint_section_id');
    }
}
