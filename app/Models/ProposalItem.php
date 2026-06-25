<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class ProposalItem extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        
        'subscription_id','proposal_id',
        'module_name',
        'description',
        'quantity',
        'unit_price',
        'total',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

}