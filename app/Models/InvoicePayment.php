<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class InvoicePayment extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        
        'subscription_id','invoice_id',
        'payment_date',
        'amount',
        'payment_mode',
        'reference_no',
        'bank_name',
        'remarks',
        'received_by',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

}