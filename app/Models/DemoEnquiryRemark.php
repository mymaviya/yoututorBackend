<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoEnquiryRemark extends Model
{
    protected $fillable = [
        'demo_enquiry_id',
        'user_id',
        'type',
        'remark',
        'follow_up_at',
    ];

    protected $casts = [
        'follow_up_at' => 'datetime',
    ];

    public function demoEnquiry()
    {
        return $this->belongsTo(DemoEnquiry::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

   
}
