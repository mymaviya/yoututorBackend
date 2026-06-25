<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use BelongsToSubscription;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'subscription_id',
        'name',
        'email',
        'contact',
        'address',
        'profile',
        'password',
        'role',
        'is_active',
        'login_enabled',
        'login_start_date',
        'login_end_date',
        'daily_login_start_time',
        'daily_login_end_time',
        'session_timeout_minutes',
        'allow_multiple_sessions',
        'current_session_id',
        'last_activity_at',
        'password_change_required',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'login_enabled' => 'boolean',
            'allow_multiple_sessions' => 'boolean',
            'password_change_required' => 'boolean',
            'login_start_date' => 'date',
            'login_end_date' => 'date',
            'last_activity_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    public function roleData()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withPivot('allowed');
    }

    public function teacherAssignments()
    {
        return $this->hasMany(TeacherAssignment::class, 'teacher_id');
    }

    public function teacherGrades()
    {
        return $this->hasMany(TeacherGrade::class, 'teacher_id');
    }

    public function questionTasks()
    {
        return $this->hasMany(TeacherQuestionTask::class, 'teacher_id');
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    public function notifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    public function hasPermission($permission)
    {
        $direct = $this->permissions()->where('slug', $permission)->first();

        if ($direct) {
            return (bool) $direct->pivot->allowed;
        }

        return $this->roleData?->permissions()->where('slug', $permission)->exists();
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function scopeTeachers($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->where('slug', 'teacher');
        });
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function teacherProfile()
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function isSuperAdmin(): bool
    {
        $role = $this->roleData?->slug ?? $this->role;

        return in_array($role, ['superadmin', 'super_admin'], true);
    }

}
