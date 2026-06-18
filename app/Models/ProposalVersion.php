<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalVersion extends Model
{
    protected $fillable = [
        'proposal_id',
        'version_no',
        'snapshot',
        'created_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}