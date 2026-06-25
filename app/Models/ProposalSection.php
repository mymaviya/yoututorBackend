<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class ProposalSection extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        
        'subscription_id','proposal_id',
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

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

}
