<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        
        'subscription_id','quotation_id',
        'proposal_id',
        'invoice_no',
        'client_name',
        'organization_name',
        'project_name',
        'invoice_date',
        'due_date',
        'subtotal',
        'gst_percentage',
        'gst_amount',
        'grand_total',
        'paid_amount',
        'balance_amount',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'gst_percentage' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)
            ->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

}
