<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Payslip;

class SystemController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:system.view_health')->only(['health']);
        $this->middleware('permission:system.view_statistics')->only(['statistics']);
        $this->middleware('permission:system.clear_cache')->only(['clearCache']);
        $this->middleware('permission:system.optimize_database')->only(['optimizeDatabase']);
        $this->middleware('permission:system.cleanup')->only(['cleanup']);
        $this->middleware('permission:system.clear_logs')->only(['clearLogs']);
    }

    /**
     * Get system health information
     */
    public function health(): JsonResponse
    {
        try {
            $queueCount = $this->getQueueCount();
            $storageUsed = $this->getStorageUsage();
            $databaseStatus = $this->checkDatabaseConnection();
            $memoryUsage = $this->getMemoryUsage();
            $diskSpace = $this->getDiskSpace();

            return response()->json([
                'status' => 'healthy',
                'queueCount' => $queueCount,
                'storageUsed' => $storageUsed,
                'database' => $databaseStatus,
                'memory' => $memoryUsage,
                'disk' => $diskSpace,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('System health check failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'status' => 'unhealthy',
                'error' => 'System health check failed',
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Get system statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'payslips' => [
                    'total' => Payslip::count(),
                    'completed' => Payslip::where('status', 'completed')->count(),
                    'failed' => Payslip::where('status', 'failed')->count(),
                    'processing' => Payslip::where('status', 'processing')->count(),
                    'queued' => Payslip::where('status', 'queued')->count(),
                ],
                'processing' => [
                    'success_rate' => $this->getSuccessRate(),
                    'avg_processing_time' => $this->getAverageProcessingTime(),
                    'files_processed_today' => $this->getProcessedToday(),
                ],
                'storage' => [
                    'total_files' => $this->getTotalFiles(),
                    'storage_used_mb' => $this->getStorageUsage(),
                    'avg_file_size_mb' => $this->getAverageFileSize(),
                ],
                'errors' => [
                    'recent_errors' => $this->getRecentErrors(),
                    'error_rate' => $this->getErrorRate(),
                ],
            ];

            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error('Failed to get system statistics', ['error' => $e->getMessage()]);
            
            return response()->json([
                'error' => 'Failed to retrieve system statistics'
            ], 500);
        }
    }

    /**
     * Clear system cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            Cache::flush();
            
            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear cache', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to clear cache'
            ], 500);
        }
    }

    /**
     * Optimize database
     */
    public function optimizeDatabase(): JsonResponse
    {
        try {
            // Run database optimization commands
            DB::statement('OPTIMIZE TABLE payslips');
            DB::statement('OPTIMIZE TABLE koperasi');
            
            return response()->json([
                'success' => true,
                'message' => 'Database optimized successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Database optimization failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => 'Database optimization failed'
            ], 500);
        }
    }

    /**
     * Clean up old files
     */
    public function cleanup(): JsonResponse
    {
        try {
            $olderThan = now()->subDays(30);
            
            // Get old payslips
            $oldPayslips = Payslip::where('created_at', '<', $olderThan)
                                 ->where('status', 'completed')
                                 ->get();
            
            $deletedCount = 0;
            $freedSpace = 0;
            
            foreach ($oldPayslips as $payslip) {
                if ($payslip->file_path && Storage::exists($payslip->file_path)) {
                    $fileSize = Storage::size($payslip->file_path);
                    Storage::delete($payslip->file_path);
                    $freedSpace += $fileSize;
                }
                
                $payslip->delete();
                $deletedCount++;
            }
            
            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$deletedCount} old files",
                'freed_space_mb' => round($freedSpace / 1024 / 1024, 2),
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Cleanup failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => 'Cleanup operation failed'
            ], 500);
        }
    }

    /**
     * Clear application logs
     */
    public function clearLogs(): JsonResponse
    {
        try {
            $logFiles = Storage::disk('local')->files('logs');
            
            foreach ($logFiles as $logFile) {
                Storage::disk('local')->delete($logFile);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Logs cleared successfully',
                'cleared_files' => count($logFiles)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear logs', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to clear logs'
            ], 500);
        }
    }

    /**
     * Get queue count
     */
    private function getQueueCount(): int
    {
        try {
            return DB::table('jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get storage usage in MB
     */
    private function getStorageUsage(): float
    {
        try {
            $totalSize = 0;
            $files = Storage::allFiles('payslips');
            
            foreach ($files as $file) {
                $totalSize += Storage::size($file);
            }
            
            return round($totalSize / 1024 / 1024, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Check database connection
     */
    private function checkDatabaseConnection(): array
    {
        try {
            DB::connection()->getPdo();
            
            return [
                'status' => 'connected',
                'connection' => DB::connection()->getName()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'disconnected',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get memory usage
     */
    private function getMemoryUsage(): array
    {
        return [
            'used_mb' => round(memory_get_usage() / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2),
            'limit' => ini_get('memory_limit')
        ];
    }

    /**
     * Get disk space information
     */
    private function getDiskSpace(): array
    {
        $bytes = disk_free_space(".");
        $si_prefix = array('B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB');
        $base = 1024;
        $class = min((int)log($bytes, $base), count($si_prefix) - 1);
        
        return [
            'free' => sprintf('%1.2f', $bytes / pow($base, $class)) . ' ' . $si_prefix[$class],
            'free_bytes' => $bytes
        ];
    }

    /**
     * Get success rate percentage
     */
    private function getSuccessRate(): float
    {
        $total = Payslip::count();
        if ($total === 0) return 0;
        
        $successful = Payslip::where('status', 'completed')->count();
        return round(($successful / $total) * 100, 2);
    }

    /**
     * Get average processing time
     */
    private function getAverageProcessingTime(): float
    {
        $payslips = Payslip::where('status', 'completed')
                          ->whereNotNull('created_at')
                          ->whereNotNull('updated_at')
                          ->get();
        
        if ($payslips->isEmpty()) return 0;
        
        $totalTime = 0;
        foreach ($payslips as $payslip) {
            $processingTime = $payslip->updated_at->diffInSeconds($payslip->created_at);
            $totalTime += $processingTime;
        }
        
        return round($totalTime / $payslips->count(), 2);
    }

    /**
     * Get files processed today
     */
    private function getProcessedToday(): int
    {
        return Payslip::whereDate('created_at', today())->count();
    }

    /**
     * Get total number of files
     */
    private function getTotalFiles(): int
    {
        return count(Storage::allFiles('payslips'));
    }

    /**
     * Get average file size in MB
     */
    private function getAverageFileSize(): float
    {
        $files = Storage::allFiles('payslips');
        if (empty($files)) return 0;
        
        $totalSize = 0;
        foreach ($files as $file) {
            $totalSize += Storage::size($file);
        }
        
        return round(($totalSize / count($files)) / 1024 / 1024, 2);
    }

    /**
     * Get recent errors
     */
    private function getRecentErrors(): int
    {
        return Payslip::where('status', 'failed')
                     ->where('created_at', '>=', now()->subHours(24))
                     ->count();
    }

    /**
     * Get error rate percentage
     */
    private function getErrorRate(): float
    {
        $total = Payslip::count();
        if ($total === 0) return 0;
        
        $failed = Payslip::where('status', 'failed')->count();
        return round(($failed / $total) * 100, 2);
    }
} 