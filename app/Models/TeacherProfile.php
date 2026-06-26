<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class TeacherProfile extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'employee_code',
        'designation',
        'qualification',
        'joining_date',
        'experience_years',
        'bio',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

}