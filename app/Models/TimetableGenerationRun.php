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

    public const STATUSES = [
        'queued',
        'running',
        'completed',
        'partial',
        'failed',
        'cancelled',
    ];

    public const MODES = ['single', 'batch'];

    protected $fillable = [
        'subscription_id',
        'user_id',
        'parent_run_id',
        'queue_job_id',
        'attempt_count',
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
        'cancellation_requested_at',
        'cancelled_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'subscription_id' => 'integer',
        'user_id' => 'integer',
        'parent_run_id' => 'integer',
        'attempt_count' => 'integer',
        'is_preview' => 'boolean',
        'progress_percentage' => 'integer',
        'requested_items' => 'integer',
        'processed_items' => 'integer',
        'successful_items' => 'integer',
        'failed_items' => 'integer',
        'request_payload' => 'array',
        'result_summary' => 'array',
        'cancellation_requested_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'partial', 'failed', 'cancelled'], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['queued', 'running'], true)
            && $this->cancellation_requested_at === null;
    }

    public function cancellationRequested(): bool
    {
        return $this->cancellation_requested_at !== null;
    }
}
