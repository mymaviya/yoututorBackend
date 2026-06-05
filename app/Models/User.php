<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'contact',
        'address',
        'profile',
        'role',
        'role_id',
        'is_active',
        'login_enabled',
        'login_start_date',
        'login_end_date',
        'daily_login_start_time',
        'daily_login_end_time',
        'current_session_id',
        'last_activity_at',
        'password_change_required',
        'password_changed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'login_enabled' => 'boolean',
            'password_change_required' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    public function teacher()
    {
        return $this->hasOne(Teacher::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withPivot('allowed');
    }

    public function hasPermission($permission)
    {
        // direct permission override

        $direct = $this->permissions()
            ->where('slug', $permission)
            ->first();

        if ($direct) {
            return (bool) $direct->pivot->allowed;
        }

        // role permission

        return $this->role?->permissions()
            ->where('slug', $permission)
            ->exists();
    }

    public function roleData()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
