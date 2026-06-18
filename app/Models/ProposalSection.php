<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalSection extends Model
{
    protected $fillable = [
        'proposal_id',
        'title',
        'section_key',
        'content',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function sections()
    {
        return $this->hasMany(ProposalSection::class)
            ->orderBy('sort_order');
    }
}
