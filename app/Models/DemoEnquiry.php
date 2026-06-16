<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoEnquiry extends Model
{
    protected $fillable = [
        'school_name',
        'contact_person',
        'mobile',
        'email',
        'school_type',
        'interested_plan',
        'message',
        'status',
        'admin_note',
        'demo_started_at',
        'demo_ends_at',
        'follow_up_date',
        'last_contact_at',
        'assigned_to',
        'converted_subscription_id',
    ];

    protected $casts = [
        'demo_started_at' => 'datetime',
        'demo_ends_at' => 'datetime',
        'follow_up_date' => 'datetime',
        'last_contact_at' => 'datetime',
    ];

    public function remarks()
    {
        return $this->hasMany(DemoEnquiryRemark::class)
            ->latest();
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class, 'converted_subscription_id');
    }
}
