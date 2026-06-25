<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        
        'subscription_id','proposal_id',
        'quotation_no',
        'client_name',
        'organization_name',
        'project_name',
        'quotation_date',
        'valid_until',
        'subtotal',
        'gst_percentage',
        'gst_amount',
        'grand_total',
        'status',
        'terms',
        'created_by',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'gst_percentage' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class)
            ->orderBy('sort_order');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

}