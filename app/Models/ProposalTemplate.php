<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalTemplate extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'project_type',
        'description',
        'is_active',
        'sort_order',
    ];

    public function sections()
    {
        return $this->hasMany(ProposalTemplateSection::class)
            ->orderBy('sort_order');
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class);
    }
}