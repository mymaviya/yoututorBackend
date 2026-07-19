<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetableGenerationRun extends Model
{
    use BelongsToSubscription;

    public const STATUSES = ['queued', 'running', 'completed', 'partial', 'failed'];
    public const MODES = ['single', 'batch'];

    protected $fillable = [
        'subscription_id',
        'user_id',
        'parent_run_id',
        'mode',
        'is_preview',
        'status',
        'progress_percentage',
        'requested_items',
        'processed_items',
        'successful_items',
        'failed_items',
        'request_payload',
        'result_summary',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'subscription_id' => 'integer',
        'user_id' => 'integer',
        'parent_run_id' => 'integer',
        'is_preview' => 'boolean',
        'progress_percentage' => 'integer',
        'requested_items' => 'integer',
        'processed_items' => 'integer',
        'successful_items' => 'integer',
        'failed_items' => 'integer',
        'request_payload' => 'array',
        'result_summary' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parentRun(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_run_id');
    }

    public function retries(): HasMany
    {
        return $this->hasMany(self::class, 'parent_run_id');
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(TimetableGenerationConflict::class);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('id');
    }
}
