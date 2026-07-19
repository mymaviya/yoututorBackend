<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
            'id' => 'integer',
            'role_id' => 'integer',
            'subscription_id' => 'integer',
            'session_timeout_minutes' => 'integer',
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

    public function roleData(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'user_permissions'
        )->withPivot('allowed');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(
            TeacherAssignment::class,
            'teacher_id'
        );
    }

    public function teacherGrades(): HasMany
    {
        return $this->hasMany(
            TeacherGrade::class,
            'teacher_id'
        );
    }

    public function questionTasks(): HasMany
    {
        return $this->hasMany(
            TeacherQuestionTask::class,
            'teacher_id'
        );
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(
            TimetableEntry::class,
            'teacher_id'
        );
    }

    public function activeTimetableEntries(): HasMany
    {
        return $this->timetableEntries()
            ->where('is_active', true);
    }

    public function substituteTimetableEntries(): HasMany
    {
        return $this->hasMany(
            TimetableEntry::class,
            'substitute_teacher_id'
        );
    }

    public function teacherAvailabilities(): HasMany
    {
        return $this->hasMany(
            TeacherAvailability::class,
            'teacher_id'
        );
    }

    public function activeTeacherAvailabilities(): HasMany
    {
        return $this->teacherAvailabilities()
            ->where('is_active', true);
    }

    public function parallelGroupItems(): HasMany
    {
        return $this->hasMany(
            ParallelGroupItem::class,
            'teacher_id'
        );
    }

    public function hasPermission($permission)
    {
        $direct = $this->permissions()
            ->where('slug', $permission)
            ->first();

        if ($direct) {
            return (bool) $direct->pivot->allowed;
        }

        return $this->roleData?->permissions()
            ->where('slug', $permission)
            ->exists();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function scopeTeachers(Builder $query): Builder
    {
        return $query->whereHas(
            'role',
            fn (Builder $roleQuery) => $roleQuery->where(
                'slug',
                'teacher'
            )
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeActiveTeachers(Builder $query): Builder
    {
        return $query->teachers()->active();
    }

    public function scopeAvailableForTimetable(
        Builder $query
    ): Builder {
        return $query
            ->activeTeachers()
            ->where(function (Builder $loginQuery) {
                $loginQuery
                    ->whereNull('login_enabled')
                    ->orWhere('login_enabled', true);
            });
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(
            Subscription::class,
            'subscription_id'
        );
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(
            TeacherProfile::class,
            'user_id'
        );
    }

    public function isTeacher(): bool
    {
        $role = $this->roleData?->slug ?? $this->role;

        return $role === 'teacher';
    }

    public function isSuperAdmin(): bool
    {
        $role = $this->roleData?->slug ?? $this->role;

        return in_array(
            $role,
            ['superadmin', 'super_admin'],
            true
        );
    }
}