<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Auth;

class ErrorHandlingService
{
    /**
     * Log an error with enhanced context.
     */
    public static function logError(\Throwable $exception, array $context = []): void
    {
        $enrichedContext = self::enrichContext($context, $exception);
        
        Log::error($exception->getMessage(), $enrichedContext);
        
        // Log critical errors to a separate channel
        if (self::isCriticalError($exception)) {
            Log::channel('critical')->error($exception->getMessage(), $enrichedContext);
        }
    }

    /**
     * Log a warning with context.
     */
    public static function logWarning(string $message, array $context = []): void
    {
        $enrichedContext = self::enrichContext($context);
        
        Log::warning($message, $enrichedContext);
    }

    /**
     * Log an info message with context.
     */
    public static function logInfo(string $message, array $context = []): void
    {
        $enrichedContext = self::enrichContext($context);
        
        Log::info($message, $enrichedContext);
    }

    /**
     * Log a security event.
     */
    public static function logSecurityEvent(string $event, array $context = []): void
    {
        $enrichedContext = self::enrichContext($context);
        $enrichedContext['event'] = $event;
        $enrichedContext['security_event'] = true;
        
        Log::channel('security')->warning("Security Event: {$event}", $enrichedContext);
    }

    /**
     * Log a performance issue.
     */
    public static function logPerformanceIssue(string $operation, float $duration, array $context = []): void
    {
        $enrichedContext = self::enrichContext($context);
        $enrichedContext['operation'] = $operation;
        $enrichedContext['duration_ms'] = $duration;
        $enrichedContext['performance_issue'] = true;
        
        Log::channel('performance')->warning("Performance Issue: {$operation} took {$duration}ms", $enrichedContext);
    }

    /**
     * Log OCR processing errors.
     */
    public static function logOcrError(\Throwable $exception, array $ocrContext = []): void
    {
        $context = array_merge([
            'error_type' => 'ocr_processing',
            'module' => 'ocr'
        ], $ocrContext);
        
        self::logError($exception, $context);
    }

    /**
     * Log API errors.
     */
    public static function logApiError(\Throwable $exception, string $endpoint, array $apiContext = []): void
    {
        $context = array_merge([
            'error_type' => 'api_error',
            'endpoint' => $endpoint,
            'module' => 'api'
        ], $apiContext);
        
        self::logError($exception, $context);
    }

    /**
     * Log database errors.
     */
    public static function logDatabaseError(\Throwable $exception, string $query = null, array $bindings = []): void
    {
        $context = [
            'error_type' => 'database_error',
            'module' => 'database'
        ];
        
        if ($query) {
            $context['query'] = $query;
        }
        
        if (!empty($bindings)) {
            $context['bindings'] = $bindings;
        }
        
        self::logError($exception, $context);
    }

    /**
     * Log file processing errors.
     */
    public static function logFileProcessingError(\Throwable $exception, string $filePath = null, array $fileContext = []): void
    {
        $context = array_merge([
            'error_type' => 'file_processing',
            'module' => 'file_processing'
        ], $fileContext);
        
        if ($filePath) {
            $context['file_path'] = $filePath;
            $context['file_size'] = file_exists($filePath) ? filesize($filePath) : null;
        }
        
        self::logError($exception, $context);
    }

    /**
     * Log authentication errors.
     */
    public static function logAuthError(string $message, array $authContext = []): void
    {
        $context = array_merge([
            'error_type' => 'authentication',
            'module' => 'auth'
        ], $authContext);
        
        self::logSecurityEvent($message, $context);
    }

    /**
     * Get error summary for dashboard.
     */
    public static function getErrorSummary(int $hours = 24): array
    {
        try {
            $logPath = storage_path('logs/laravel.log');
            if (!file_exists($logPath)) {
                return ['total' => 0, 'by_type' => [], 'recent' => []];
            }

            $errors = [];
            $since = now()->subHours($hours);
            
            // This is a simplified version - in production you might want to use
            // a proper log aggregation service like ELK stack
            $logContents = file_get_contents($logPath);
            $lines = explode("\n", $logContents);
            
            foreach (array_reverse($lines) as $line) {
                if (str_contains($line, 'ERROR') || str_contains($line, 'CRITICAL')) {
                    $errors[] = $line;
                    if (count($errors) >= 100) break; // Limit to last 100 errors
                }
            }
            
            return [
                'total' => count($errors),
                'by_type' => self::categorizeErrors($errors),
                'recent' => array_slice($errors, 0, 10),
                'period_hours' => $hours
            ];
            
        } catch (\Exception $e) {
            return ['total' => 0, 'by_type' => [], 'recent' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Enrich context with additional information.
     */
    private static function enrichContext(array $context = [], \Throwable $exception = null): array
    {
        $enriched = [
            'timestamp' => now()->toISOString(),
            'environment' => config('app.env', 'unknown'),
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'user_id' => Auth::id(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];

        if ($exception) {
            $enriched = array_merge($enriched, [
                'exception_class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'previous' => $exception->getPrevious() ? get_class($exception->getPrevious()) : null,
            ]);
        }

        return array_merge($enriched, $context);
    }

    /**
     * Determine if an error is critical.
     */
    private static function isCriticalError(\Throwable $exception): bool
    {
        $criticalExceptions = [
            \OutOfMemoryError::class,
            \Error::class,
            \PDOException::class,
        ];

        foreach ($criticalExceptions as $criticalException) {
            if ($exception instanceof $criticalException) {
                return true;
            }
        }

        // Check if error message contains critical keywords
        $criticalKeywords = ['fatal', 'memory exhausted', 'database connection', 'security breach'];
        $message = strtolower($exception->getMessage());
        
        foreach ($criticalKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Categorize errors by type.
     */
    private static function categorizeErrors(array $errors): array
    {
        $categories = [
            'database' => 0,
            'api' => 0,
            'ocr' => 0,
            'file_processing' => 0,
            'authentication' => 0,
            'validation' => 0,
            'other' => 0
        ];

        foreach ($errors as $error) {
            $error = strtolower($error);
            
            if (str_contains($error, 'database') || str_contains($error, 'sql') || str_contains($error, 'pdo')) {
                $categories['database']++;
            } elseif (str_contains($error, 'api') || str_contains($error, 'endpoint')) {
                $categories['api']++;
            } elseif (str_contains($error, 'ocr') || str_contains($error, 'tesseract')) {
                $categories['ocr']++;
            } elseif (str_contains($error, 'file') || str_contains($error, 'upload')) {
                $categories['file_processing']++;
            } elseif (str_contains($error, 'auth') || str_contains($error, 'login') || str_contains($error, 'permission')) {
                $categories['authentication']++;
            } elseif (str_contains($error, 'validation') || str_contains($error, 'invalid')) {
                $categories['validation']++;
            } else {
                $categories['other']++;
            }
        }

        return $categories;
    }
} 