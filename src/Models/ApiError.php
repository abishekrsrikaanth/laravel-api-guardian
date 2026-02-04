<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ApiError extends Model
{
    use HasFactory;

    protected $table = 'api_guardian_errors';

    protected $fillable = [
        'error_id',
        'exception_class',
        'error_code',
        'message',
        'status_code',
        'context',
        'meta',
        'request_method',
        'request_url',
        'request_headers',
        'request_data',
        'ip_address',
        'user_agent',
        'user_id',
        'is_resolved',
        'occurrence_count',
        'first_occurred_at',
        'last_occurred_at',
        'resolved_at',
    ];

    protected $casts = [
        'context' => 'array',
        'meta' => 'array',
        'request_headers' => 'array',
        'request_data' => 'array',
        'is_resolved' => 'boolean',
        'occurrence_count' => 'integer',
        'first_occurred_at' => 'datetime',
        'last_occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * @return HasMany<ErrorTrend, $this>
     */
    public function trends(): HasMany
    {
        return $this->hasMany(ErrorTrend::class, 'error_code', 'error_code');
    }

    /**
     * Mark error as resolved
     */
    public function resolve(): void
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Increment occurrence count
     */
    public function incrementOccurrence(): void
    {
        $this->increment('occurrence_count');
        $this->update(['last_occurred_at' => now()]);
    }

    /**
     * Scope to filter unresolved errors
     */
    protected function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Scope to filter resolved errors
     */
    protected function scopeResolved(Builder $query): Builder
    {
        return $query->where('is_resolved', true);
    }

    /**
     * Scope to filter by status code
     */
    protected function scopeStatusCode(Builder $query, int $statusCode): Builder
    {
        return $query->where('status_code', $statusCode);
    }

    /**
     * Scope to filter by error code
     */
    protected function scopeErrorCode(Builder $query, string $errorCode): Builder
    {
        return $query->where('error_code', $errorCode);
    }

    /**
     * Scope to filter recent errors
     */
    protected function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
