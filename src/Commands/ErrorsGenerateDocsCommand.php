<?php

namespace WorkDoneRight\ApiGuardian\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ErrorsGenerateDocsCommand extends Command
{
    protected $signature = 'errors:generate-docs
                            {--format=markdown : Output format (markdown, html, openapi)}
                            {--output= : Output path}';

    protected $description = 'Generate API error documentation';

    public function handle(): int
    {
        $format = $this->option('format');
        $output = $this->option('output') ?? config('api-guardian.documentation.output_path');

        $this->info("Generating {$format} documentation...");

        // Ensure output directory exists
        if (! File::exists($output)) {
            File::makeDirectory($output, 0755, true);
        }

        match ($format) {
            'markdown' => $this->generateMarkdown($output),
            'html' => $this->generateHtml($output),
            'openapi' => $this->generateOpenApi($output),
            default => $this->error("Unsupported format: {$format}"),
        };

        $this->info("Documentation generated at: {$output}");

        return self::SUCCESS;
    }

    protected function generateMarkdown(string $output): void
    {
        $content = $this->buildMarkdownContent();
        File::put($output.'/errors.md', $content);
    }

    protected function buildMarkdownContent(): string
    {
        $content = "# API Error Reference\n\n";
        $content .= "This document lists all possible API errors and their meanings.\n\n";

        $content .= "## Standard Error Codes\n\n";
        $content .= "| Code | Status | Description |\n";
        $content .= "|------|--------|-------------|\n";
        $content .= "| `BAD_REQUEST` | 400 | The request was invalid |\n";
        $content .= "| `UNAUTHORIZED` | 401 | Authentication is required |\n";
        $content .= "| `FORBIDDEN` | 403 | You don't have permission |\n";
        $content .= "| `RESOURCE_NOT_FOUND` | 404 | The resource doesn't exist |\n";
        $content .= "| `VALIDATION_FAILED` | 422 | Input validation failed |\n";
        $content .= "| `RATE_LIMIT_EXCEEDED` | 429 | Too many requests |\n";
        $content .= "| `SERVER_ERROR` | 500 | Internal server error |\n";

        $content .= "\n## Error Response Formats\n\n";
        $content .= "### JSend Format\n\n";
        $content .= "```json\n";
        $content .= $this->getJSendExample();
        $content .= "\n```\n\n";

        $content .= "### RFC 7807 Format\n\n";
        $content .= "```json\n";
        $content .= $this->getRfc7807Example();
        $content .= "\n```\n\n";

        return $content;
    }

    protected function getJSendExample(): string
    {
        return json_encode([
            'status' => 'fail',
            'message' => 'Resource not found',
            'code' => 'RESOURCE_NOT_FOUND',
            'data' => [
                'error_id' => 'err_abc123',
                'timestamp' => '2026-01-31T12:00:00Z',
            ],
        ], JSON_PRETTY_PRINT);
    }

    protected function getRfc7807Example(): string
    {
        return json_encode([
            'type' => 'https://api.example.com/errors/resource-not-found',
            'title' => 'Not Found',
            'status' => 404,
            'detail' => 'Resource not found',
            'instance' => 'err_abc123',
        ], JSON_PRETTY_PRINT);
    }

    protected function generateHtml(string $output): void
    {
        $content = $this->buildHtmlContent();
        File::put($output.'/errors.html', $content);
    }

    protected function buildHtmlContent(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Error Reference</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f5f5f5; font-weight: 600; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: 'Monaco', monospace; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>API Error Reference</h1>
    <p>This document lists all possible API errors and their meanings.</p>

    <h2>Standard Error Codes</h2>
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Status</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><code>BAD_REQUEST</code></td><td>400</td><td>The request was invalid</td></tr>
            <tr><td><code>UNAUTHORIZED</code></td><td>401</td><td>Authentication is required</td></tr>
            <tr><td><code>FORBIDDEN</code></td><td>403</td><td>You don't have permission</td></tr>
            <tr><td><code>RESOURCE_NOT_FOUND</code></td><td>404</td><td>The resource doesn't exist</td></tr>
            <tr><td><code>VALIDATION_FAILED</code></td><td>422</td><td>Input validation failed</td></tr>
            <tr><td><code>RATE_LIMIT_EXCEEDED</code></td><td>429</td><td>Too many requests</td></tr>
            <tr><td><code>SERVER_ERROR</code></td><td>500</td><td>Internal server error</td></tr>
        </tbody>
    </table>
</body>
</html>
HTML;
    }

    protected function generateOpenApi(string $output): void
    {
        $content = $this->buildOpenApiContent();
        File::put($output.'/errors.yaml', $content);
    }

    protected function buildOpenApiContent(): string
    {
        return <<<'YAML'
components:
  schemas:
    Error:
      type: object
      properties:
        status:
          type: string
          enum: [fail, error]
        message:
          type: string
        code:
          type: string
        data:
          type: object

  responses:
    BadRequest:
      description: Bad Request
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'

    Unauthorized:
      description: Unauthorized
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'

    NotFound:
      description: Not Found
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
YAML;
    }
}
