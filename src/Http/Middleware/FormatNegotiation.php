<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

final class FormatNegotiation
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ?string $format = null): mixed
    {
        // Use format from route parameter if provided
        if ($format) {
            ApiGuardian::useFormatter($format);

            return $next($request);
        }

        // Detect a format from Accept header
        $acceptHeader = $request->header('Accept', '');

        $detectedFormat = $this->detectFormat($acceptHeader);

        if ($detectedFormat) {
            ApiGuardian::useFormatter($detectedFormat);
        }

        return $next($request);
    }

    /**
     * Detect a format from Accept header.
     */
    private function detectFormat(string $acceptHeader): ?string
    {
        // Check for specific format hints in the Accept header
        if (str_contains($acceptHeader, 'application/problem+json')) {
            return 'rfc7807';
        }

        if (str_contains($acceptHeader, 'application/vnd.api+json')) {
            return 'jsonapi';
        }

        // Default to configured format
        return null;
    }
}
