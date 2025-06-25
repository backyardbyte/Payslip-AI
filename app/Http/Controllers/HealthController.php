<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    /**
     * Application health check endpoint.
     */
    public function check(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'checks' => []
        ];

        // Database connectivity
        try {
            DB::connection()->getPdo();
            $health['checks']['database'] = [
                'status' => 'healthy',
                'message' => 'Database connection successful'
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['database'] = [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }

        // Queue functionality
        try {
            $queuedJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            
            $health['checks']['queue'] = [
                'status' => 'healthy',
                'message' => "Queue operational",
                'queued_jobs' => $queuedJobs,
                'failed_jobs' => $failedJobs
            ];

            if ($failedJobs > 10) {
                $health['checks']['queue']['status'] = 'warning';
                $health['checks']['queue']['message'] = "High number of failed jobs: $failedJobs";
            }
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['queue'] = [
                'status' => 'unhealthy',
                'message' => 'Queue check failed: ' . $e->getMessage()
            ];
        }

        // Storage accessibility
        try {
            Storage::disk('local')->put('health-check.txt', 'test');
            Storage::disk('local')->delete('health-check.txt');
            
            $health['checks']['storage'] = [
                'status' => 'healthy',
                'message' => 'Storage is writable'
            ];
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['storage'] = [
                'status' => 'unhealthy',
                'message' => 'Storage write failed: ' . $e->getMessage()
            ];
        }

        // Cache functionality
        try {
            $testKey = 'health-check-' . time();
            Cache::put($testKey, 'test', 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);
            
            if ($retrieved === 'test') {
                $health['checks']['cache'] = [
                    'status' => 'healthy',
                    'message' => 'Cache is working'
                ];
            } else {
                $health['checks']['cache'] = [
                    'status' => 'unhealthy',
                    'message' => 'Cache read/write failed'
                ];
            }
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['cache'] = [
                'status' => 'unhealthy',
                'message' => 'Cache check failed: ' . $e->getMessage()
            ];
        }

        // OCR dependencies
        $ocrStatus = $this->checkOCRDependencies();
        $health['checks']['ocr'] = $ocrStatus;
        if ($ocrStatus['status'] !== 'healthy') {
            $health['status'] = 'degraded';
        }

        // Recent processing activity
        try {
            $recentProcessed = Payslip::where('processing_completed_at', '>=', now()->subHour())
                ->count();
            
            $health['checks']['processing'] = [
                'status' => 'healthy',
                'message' => "Processed $recentProcessed payslips in the last hour",
                'recent_processed' => $recentProcessed
            ];
        } catch (\Exception $e) {
            $health['checks']['processing'] = [
                'status' => 'unknown',
                'message' => 'Could not check processing activity: ' . $e->getMessage()
            ];
        }

        $statusCode = match($health['status']) {
            'healthy' => 200,
            'degraded' => 200,
            'unhealthy' => 503,
            default => 500
        };

        return response()->json($health, $statusCode);
    }

    /**
     * Check OCR dependencies.
     */
    private function checkOCRDependencies(): array
    {
        $issues = [];
        
        // Check tesseract
        if (!$this->commandExists('tesseract')) {
            $issues[] = 'Tesseract OCR not found';
        }
        
        // Check pdftotext
        if (!$this->commandExists('pdftotext')) {
            $issues[] = 'pdftotext not found';
        }

        if (empty($issues)) {
            return [
                'status' => 'healthy',
                'message' => 'All OCR dependencies available'
            ];
        }

        return [
            'status' => 'unhealthy',
            'message' => 'OCR dependencies missing: ' . implode(', ', $issues),
            'missing' => $issues
        ];
    }

    /**
     * Check if a command exists.
     */
    private function commandExists(string $command): bool
    {
        $output = shell_exec("which $command 2>/dev/null");
        return !empty($output);
    }

    /**
     * Detailed system status for monitoring.
     */
    public function status(): JsonResponse
    {
        $stats = [
            'application' => [
                'name' => config('app.name'),
                'environment' => config('app.env'),
                'debug' => config('app.debug'),
                'version' => '1.0.0', // You can get this from your package.json or composer.json
            ],
            'database' => [
                'connection' => config('database.default'),
                'migrations' => $this->getMigrationStatus(),
            ],
            'queue' => [
                'default_connection' => config('queue.default'),
                'jobs_pending' => DB::table('jobs')->count(),
                'jobs_failed' => DB::table('failed_jobs')->count(),
                'jobs_processed_today' => Payslip::whereDate('processing_completed_at', today())->count(),
            ],
            'storage' => [
                'disk' => config('filesystems.default'),
                'total_payslips' => Payslip::count(),
                'storage_size' => $this->getStorageSize(),
            ],
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                'memory_peak' => $this->formatBytes(memory_get_peak_usage(true)),
            ]
        ];

        return response()->json($stats);
    }

    /**
     * Get migration status.
     */
    private function getMigrationStatus(): array
    {
        try {
            $migrations = DB::table('migrations')->count();
            return [
                'status' => 'up_to_date',
                'total_migrations' => $migrations
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get storage size.
     */
    private function getStorageSize(): string
    {
        try {
            $path = Storage::path('payslips');
            if (is_dir($path)) {
                $size = $this->getDirectorySize($path);
                return $this->formatBytes($size);
            }
            return '0 B';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get directory size recursively.
     */
    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        foreach (glob($directory . '/*', GLOB_NOSORT) as $file) {
            $size += is_file($file) ? filesize($file) : $this->getDirectorySize($file);
        }
        return $size;
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $size, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
} 