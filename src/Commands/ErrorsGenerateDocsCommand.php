<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;
use WorkDoneRight\ApiGuardian\Exceptions\ApiException;
use WorkDoneRight\ApiGuardian\Services\ErrorCollector;

final class ErrorsGenerateDocsCommand extends Command
{
    protected $signature = 'errors:generate-docs
                            {--format=markdown : Output format (markdown, html, openapi)}
                            {--output= : Output directory path}
                            {--include-stats : Include error statistics}
                            {--scan-app : Scan app exceptions directory}';

    protected $description = 'Generate comprehensive API error documentation';

    public function __construct(
        private readonly ErrorCollector $errorCollector
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = $this->option('format');
        $output = $this->option('output') ?? storage_path('app/docs');
        $includeStats = $this->option('include-stats');

        $this->info("Generating {$format} documentation...");

        // Ensure output directory exists
        if (! File::exists($output)) {
            File::makeDirectory($output, 0755, true);
            $this->line("Created directory: {$output}");
        }

        // Discover exceptions
        $exceptions = $this->discoverExceptions();

        // Get statistics if requested
        $stats = $includeStats ? $this->errorCollector->getAnalytics(30) : null;
        $topErrors = $includeStats ? $this->errorCollector->getTopErrors(10, 30) : null;

        // Generate documentation
        $filename = match ($format) {
            'markdown' => $this->generateMarkdown($output, $exceptions, $stats, $topErrors),
            'html' => $this->generateHtml($output, $exceptions, $stats),
            'openapi' => $this->generateOpenApi($output, $exceptions),
            default => throw new InvalidArgumentException("Unsupported format: {$format}"),
        };

        $this->info('✅ Documentation generated successfully!');
        $this->line("   File: <fg=cyan>{$filename}</>");

        return self::SUCCESS;
    }

    private function discoverExceptions(): array
    {
        $exceptions = $this->getStandardExceptions();

        // Scan app exceptions if requested
        if ($this->option('scan-app')) {
            $appExceptions = $this->scanAppExceptions();
            $exceptions = array_merge($exceptions, $appExceptions);
        }

        return $exceptions;
    }

    private function getStandardExceptions(): array
    {
        return [
            [
                'code' => 'BAD_REQUEST',
                'status' => 400,
                'title' => 'Bad Request',
                'description' => 'The request was invalid or malformed',
                'example' => 'Invalid JSON payload',
            ],
            [
                'code' => 'UNAUTHORIZED',
                'status' => 401,
                'title' => 'Unauthorized',
                'description' => 'Authentication is required to access this resource',
                'example' => 'Missing or invalid API token',
            ],
            [
                'code' => 'FORBIDDEN',
                'status' => 403,
                'title' => 'Forbidden',
                'description' => 'You do not have permission to access this resource',
                'example' => 'Insufficient privileges',
            ],
            [
                'code' => 'RESOURCE_NOT_FOUND',
                'status' => 404,
                'title' => 'Not Found',
                'description' => 'The requested resource does not exist',
                'example' => 'User ID not found',
            ],
            [
                'code' => 'VALIDATION_FAILED',
                'status' => 422,
                'title' => 'Validation Failed',
                'description' => 'The request data failed validation',
                'example' => 'Email field is required',
            ],
            [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'status' => 429,
                'title' => 'Rate Limit Exceeded',
                'description' => 'Too many requests, please slow down',
                'example' => 'Maximum 100 requests per minute',
            ],
            [
                'code' => 'SERVER_ERROR',
                'status' => 500,
                'title' => 'Internal Server Error',
                'description' => 'An unexpected error occurred on the server',
                'example' => 'Database connection failed',
            ],
        ];
    }

    private function scanAppExceptions(): array
    {
        $exceptions = [];
        $exceptionsPath = app_path('Exceptions');

        if (! File::exists($exceptionsPath)) {
            return [];
        }

        $files = File::allFiles($exceptionsPath);

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);

            if (! $className) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($className);

                if ($reflection->isSubclassOf(ApiException::class) && ! $reflection->isAbstract()) {
                    $exceptions[] = $this->extractExceptionInfo($reflection);
                }
            } catch (Throwable) {
                // Skip invalid classes
            }
        }

        return $exceptions;
    }

    private function getClassNameFromFile($file): ?string
    {
        $content = $file->getContents();

        if (preg_match('/namespace\s+([^;]+);/', (string) $content, $nsMatches)) {
            $namespace = $nsMatches[1];
            $class = $file->getFilenameWithoutExtension();

            return $namespace.'\\'.$class;
        }

        return null;
    }

    private function extractExceptionInfo(ReflectionClass $reflection): array
    {
        $docComment = $reflection->getDocComment();
        $description = '';

        if ($docComment && preg_match('/@description\s+(.+)$/m', $docComment, $matches)) {
            $description = mb_trim($matches[1]);
        }

        return [
            'code' => Str::snake(class_basename($reflection->getName()), '_'),
            'status' => 500,
            'title' => class_basename($reflection->getName()),
            'description' => $description ?: 'Custom application error',
            'example' => 'See application documentation',
            'class' => $reflection->getName(),
        ];
    }

    private function generateMarkdown(string $output, array $exceptions, ?array $stats, ?array $topErrors): string
    {
        $content = "# API Error Reference\n\n";
        $content .= '*Generated: '.now()->toDateTimeString()."*\n\n";
        $content .= "This document lists all possible API errors and their meanings.\n\n";

        // Statistics
        if ($stats) {
            $content .= "## Error Statistics (Last 30 Days)\n\n";
            $content .= '- **Total Errors:** '.($stats['total_errors'] ?? 0)."\n";
            $content .= '- **Unique Errors:** '.($stats['unique_errors'] ?? 0)."\n";
            $content .= '- **Unresolved:** '.($stats['unresolved_count'] ?? 0)."\n";
            $content .= '- **Resolved:** '.($stats['resolved_count'] ?? 0)."\n\n";
        }

        // Top errors
        if ($topErrors && count($topErrors) > 0) {
            $content .= "## Top Errors\n\n";
            $content .= "| Rank | Error | Count |\n";
            $content .= "|------|-------|-------|\n";

            foreach (array_slice($topErrors, 0, 5) as $index => $error) {
                $message = $error['message'] ?? 'Unknown';
                $count = $error['occurrence_count'] ?? $error['count'] ?? 0;
                $content .= '| '.($index + 1)." | {$message} | {$count} |\n";
            }

            $content .= "\n";
        }

        // Error codes
        $content .= "## Standard Error Codes\n\n";
        $content .= "| Code | Status | Description | Example |\n";
        $content .= "|------|--------|-------------|----------|\n";

        foreach ($exceptions as $exception) {
            $content .= sprintf(
                "| `%s` | %d | %s | %s |\n",
                $exception['code'],
                $exception['status'],
                $exception['description'],
                $exception['example']
            );
        }

        // Response formats
        $content .= "\n## Error Response Formats\n\n";
        $content .= "### JSend Format\n\n";
        $content .= "```json\n".$this->getJSendExample()."\n```\n\n";
        $content .= "### RFC 7807 Format\n\n";
        $content .= "```json\n".$this->getRfc7807Example()."\n```\n\n";
        $content .= "### JSON:API Format\n\n";
        $content .= "```json\n".$this->getJsonApiExample()."\n```\n\n";

        $filename = $output.'/errors.md';
        File::put($filename, $content);

        return $filename;
    }

    private function generateHtml(string $output, array $exceptions, ?array $stats): string
    {
        $statsHtml = '';
        if ($stats) {
            $statsHtml = <<<HTML
            <section>
                <h2>Error Statistics (Last 30 Days)</h2>
                <ul>
                    <li><strong>Total Errors:</strong> {$stats['total_errors']}</li>
                    <li><strong>Unique Errors:</strong> {$stats['unique_errors']}</li>
                    <li><strong>Unresolved:</strong> {$stats['unresolved_count']}</li>
                    <li><strong>Resolved:</strong> {$stats['resolved_count']}</li>
                </ul>
            </section>
            HTML;
        }

        $exceptionsHtml = '';
        foreach ($exceptions as $exception) {
            $exceptionsHtml .= sprintf(
                "<tr><td><code>%s</code></td><td>%d</td><td>%s</td><td>%s</td></tr>\n",
                $exception['code'],
                $exception['status'],
                $exception['description'],
                $exception['example']
            );
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Error Reference</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; line-height: 1.6; }
        h1 { color: #333; border-bottom: 3px solid #e74c3c; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f5f5f5; font-weight: 600; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: 'Monaco', monospace; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .timestamp { color: #999; font-size: 0.9em; }
    </style>
</head>
<body>
    <h1>API Error Reference</h1>
    <p class="timestamp">Generated: {$this->now()->toDateTimeString()}</p>

    {$statsHtml}

    <h2>Standard Error Codes</h2>
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Status</th>
                <th>Description</th>
                <th>Example</th>
            </tr>
        </thead>
        <tbody>
            {$exceptionsHtml}
        </tbody>
    </table>

    <h2>Error Response Formats</h2>
    
    <h3>JSend Format</h3>
    <pre><code>{$this->getJSendExample()}</code></pre>
    
    <h3>RFC 7807 Format</h3>
    <pre><code>{$this->getRfc7807Example()}</code></pre>
    
    <h3>JSON:API Format</h3>
    <pre><code>{$this->getJsonApiExample()}</code></pre>
</body>
</html>
HTML;

        $filename = $output.'/errors.html';
        File::put($filename, $html);

        return $filename;
    }

    private function generateOpenApi(string $output, array $exceptions): string
    {
        $errorResponses = '';

        foreach ($exceptions as $exception) {
            $name = Str::studly($exception['code']);

            $errorResponses .= <<<YAML
    {$name}:
      description: {$exception['description']}
      content:
        application/json:
          schema:
            \$ref: '#/components/schemas/Error'
          example:
            status: 'fail'
            code: '{$exception['code']}'
            message: '{$exception['example']}'
            data:
              error_id: 'err_abc123'
              timestamp: '2026-02-03T12:00:00Z'

YAML;
        }

        $yaml = <<<YAML
openapi: 3.0.0
info:
  title: API Error Reference
  version: 1.0.0
  description: Complete API error documentation

components:
  schemas:
    Error:
      type: object
      required:
        - status
        - message
        - code
      properties:
        status:
          type: string
          enum: [fail, error]
          description: Error status
        message:
          type: string
          description: Human-readable error message
        code:
          type: string
          description: Machine-readable error code
        data:
          type: object
          description: Additional error data

  responses:
{$errorResponses}
YAML;

        $filename = $output.'/errors-openapi.yaml';
        File::put($filename, $yaml);

        return $filename;
    }

    private function getJSendExample(): string
    {
        return json_encode([
            'status' => 'fail',
            'message' => 'Resource not found',
            'code' => 'RESOURCE_NOT_FOUND',
            'data' => [
                'error_id' => 'err_abc123',
                'timestamp' => '2026-02-03T12:00:00Z',
            ],
        ], JSON_PRETTY_PRINT);
    }

    private function getRfc7807Example(): string
    {
        return json_encode([
            'type' => 'https://api.example.com/errors/resource-not-found',
            'title' => 'Not Found',
            'status' => 404,
            'detail' => 'Resource not found',
            'instance' => 'err_abc123',
        ], JSON_PRETTY_PRINT);
    }

    private function getJsonApiExample(): string
    {
        return json_encode([
            'errors' => [
                [
                    'id' => 'err_abc123',
                    'status' => '404',
                    'code' => 'RESOURCE_NOT_FOUND',
                    'title' => 'Not Found',
                    'detail' => 'Resource not found',
                ],
            ],
        ], JSON_PRETTY_PRINT);
    }

    private function now(): \Carbon\CarbonInterface
    {
        return now();
    }
}
