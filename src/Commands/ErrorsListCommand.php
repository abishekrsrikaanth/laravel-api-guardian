<?php

namespace WorkDoneRight\ApiGuardian\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use WorkDoneRight\ApiGuardian\Exceptions\ApiException;

class ErrorsListCommand extends Command
{
    protected $signature = 'errors:list
                            {--format=table : Output format (table, json)}';

    protected $description = 'List all registered API error types';

    public function handle(): int
    {
        $this->info('Scanning for API exceptions...');

        $exceptions = $this->discoverExceptions();

        if (empty($exceptions)) {
            $this->warn('No API exceptions found.');

            return self::SUCCESS;
        }

        $format = $this->option('format');

        if ($format === 'json') {
            $this->line(json_encode($exceptions, JSON_PRETTY_PRINT));
        } else {
            $this->displayTable($exceptions);
        }

        return self::SUCCESS;
    }

    protected function discoverExceptions(): array
    {
        $exceptions = [];

        // Scan app exceptions directory
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
            } catch (\Throwable $e) {
                // Skip invalid classes
            }
        }

        return $exceptions;
    }

    protected function getClassNameFromFile($file): ?string
    {
        $namespace = $this->extractNamespace($file->getContents());
        $class = $file->getFilenameWithoutExtension();

        if ($namespace) {
            return $namespace . '\\' . $class;
        }

        return null;
    }

    protected function extractNamespace(string $content): ?string
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function extractExceptionInfo(ReflectionClass $reflection): array
    {
        $instance = $reflection->newInstanceWithoutConstructor();

        return [
            'class' => $reflection->getName(),
            'code' => method_exists($instance, 'getErrorCode') ? $instance->getErrorCode() : 'N/A',
            'status' => method_exists($instance, 'getStatusCode') ? $instance->getStatusCode() : 500,
            'file' => $reflection->getFileName(),
        ];
    }

    protected function displayTable(array $exceptions): void
    {
        $this->table(
            ['Class', 'Error Code', 'Status Code', 'File'],
            array_map(function ($exception) {
                return [
                    $exception['class'],
                    $exception['code'],
                    $exception['status'],
                    str_replace(base_path(), '', $exception['file']),
                ];
            }, $exceptions)
        );

        $this->info('Total: ' . count($exceptions) . ' exception(s) found.');
    }
}
