<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaperBlueprintBloomLevel extends Model
{
    protected $fillable = [
        'paper_blueprint_id',
        'bloom_level',
        'percentage',

    ];

    protected $casts = [
        'percentage' => 'float',
    ];

    public function blueprint()
    {
        return $this->belongsTo(PaperBlueprint::class,'paper_blueprint_id');
    }
}
