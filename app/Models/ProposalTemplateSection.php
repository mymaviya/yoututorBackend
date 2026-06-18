<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalTemplateSection extends Model
{
    protected $fillable = [
        'proposal_template_id',
        'title',
        'section_key',
        'content',
        'sort_order',
        'is_editable',
    ];

    public function template()
    {
        return $this->belongsTo(ProposalTemplate::class, 'proposal_template_id');
    }
}