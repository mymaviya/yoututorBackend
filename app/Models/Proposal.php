<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    protected $fillable = [
        'proposal_template_id',
        'proposal_no',
        'client_name',
        'client_email',
        'client_phone',
        'organization_name',
        'project_name',
        'project_type',
        'timeline_days',
        'gst_applicable',
        'gst_percentage',
        'subtotal',
        'gst_amount',
        'grand_total',
        'payment_terms',
        'notes',
        'status',
        'created_by',
        'sent_at',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'gst_applicable' => 'boolean',
        'gst_percentage' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'sent_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(ProposalTemplate::class, 'proposal_template_id');
    }

    public function sections()
    {
        return $this->hasMany(ProposalSection::class)
            ->orderBy('sort_order');
    }

    public function items()
    {
        return $this->hasMany(ProposalItem::class)
            ->orderBy('sort_order');
    }

    public function versions()
    {
        return $this->hasMany(ProposalVersion::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}