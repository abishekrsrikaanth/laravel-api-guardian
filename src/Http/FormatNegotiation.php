<?php

namespace WorkDoneRight\ApiGuardian\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use WorkDoneRight\ApiGuardian\Facades\ApiGuardian;

class FormatNegotiation
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

        // Detect format from Accept header
        $acceptHeader = $request->header('Accept', '');

        $detectedFormat = $this->detectFormat($acceptHeader);

        if ($detectedFormat) {
            ApiGuardian::useFormatter($detectedFormat);
        }

        return $next($request);
    }

    /**
     * Detect format from Accept header.
     */
    protected function detectFormat(string $acceptHeader): ?string
    {
        // Check for specific format hints in Accept header
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
