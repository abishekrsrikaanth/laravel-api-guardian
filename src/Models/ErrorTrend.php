<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class ErrorTrend extends Model
{
    use HasFactory;

    protected $table = 'api_guardian_error_trends';

    protected $fillable = [
        'date',
        'error_code',
        'status_code',
        'count',
        'hourly_distribution',
    ];

    protected $casts = [
        'date' => 'date',
        'count' => 'integer',
        'hourly_distribution' => 'array',
    ];
}
