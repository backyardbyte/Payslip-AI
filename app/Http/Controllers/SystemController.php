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
    public function clearCache(Request $request): JsonResponse
    {
        try {
            $cacheType = $request->get('type', 'all');
            
            switch ($cacheType) {
                case 'application':
                    Cache::flush();
                    break;
                case 'routes':
                    \Artisan::call('route:clear');
                    \Artisan::call('route:cache');
                    break;
                case 'config':
                    \Artisan::call('config:clear');
                    \Artisan::call('config:cache');
                    break;
                case 'views':
                    \Artisan::call('view:clear');
                    break;
                case 'all':
                default:
                    Cache::flush();
                    \Artisan::call('route:clear');
                    \Artisan::call('config:clear');
                    \Artisan::call('view:clear');
                    break;
            }
            
            return response()->json([
                'success' => true,
                'message' => ucfirst($cacheType) . ' cache cleared successfully',
                'type' => $cacheType
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear cache', [
                'type' => $cacheType ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Optimize database
     */
    public function optimizeDatabase(): JsonResponse
    {
        try {
            $optimizedTables = [];
            $tables = ['payslips', 'users', 'koperasi', 'notifications', 'jobs', 'roles', 'permissions'];
            
            foreach ($tables as $table) {
                try {
                    DB::statement("OPTIMIZE TABLE {$table}");
                    $optimizedTables[] = $table;
                } catch (\Exception $e) {
                    Log::warning("Failed to optimize table {$table}: " . $e->getMessage());
                }
            }
            
            // Clear query cache
            DB::statement('RESET QUERY CACHE');
            
            return response()->json([
                'success' => true,
                'message' => 'Database optimization completed',
                'optimized_tables' => $optimizedTables,
                'total_tables' => count($optimizedTables)
            ]);
        } catch (\Exception $e) {
            Log::error('Database optimization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Database optimization failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up old files and data
     */
    public function cleanup(Request $request): JsonResponse
    {
        try {
            $cleanupType = $request->get('type', 'temp');
            $days = $request->get('days', 30);
            $olderThan = now()->subDays($days);
            
            $results = [];
            
            switch ($cleanupType) {
                case 'temp':
                    $results = $this->cleanupTempFiles($olderThan);
                    break;
                case 'logs':
                    $results = $this->cleanupLogs($olderThan);
                    break;
                case 'payslips':
                    $results = $this->cleanupOldPayslips($olderThan);
                    break;
                case 'all':
                default:
                    $tempResults = $this->cleanupTempFiles($olderThan);
                    $logResults = $this->cleanupLogs($olderThan);
                    $payslipResults = $this->cleanupOldPayslips($olderThan);
                    
                    $results = [
                        'temp_files' => $tempResults,
                        'logs' => $logResults,
                        'payslips' => $payslipResults,
                        'total_freed_space_mb' => round(
                            ($tempResults['freed_space'] + $logResults['freed_space'] + $payslipResults['freed_space']) / 1024 / 1024, 
                            2
                        ),
                        'total_deleted_count' => $tempResults['deleted_count'] + $logResults['deleted_count'] + $payslipResults['deleted_count']
                    ];
                    break;
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Cleanup completed successfully',
                'type' => $cleanupType,
                'days' => $days,
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            Log::error('Cleanup failed', [
                'type' => $cleanupType ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Cleanup operation failed',
                'error' => $e->getMessage()
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

    /**
     * Create database backup.
     */
    public function createBackup(): JsonResponse
    {
        try {
            $backupPath = storage_path('backups');
            
            if (!file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
            }
            
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $fullPath = $backupPath . '/' . $filename;
            
            $dbHost = config('database.connections.mysql.host');
            $dbName = config('database.connections.mysql.database');
            $dbUser = config('database.connections.mysql.username');
            $dbPass = config('database.connections.mysql.password');
            
            $command = sprintf(
                'mysqldump -h%s -u%s -p%s %s > %s',
                escapeshellarg($dbHost),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName),
                escapeshellarg($fullPath)
            );
            
            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);
            
            if ($returnVar === 0 && file_exists($fullPath)) {
                $fileSize = filesize($fullPath);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Database backup created successfully',
                    'backup_file' => $filename,
                    'file_size_mb' => round($fileSize / 1024 / 1024, 2),
                    'created_at' => now()->toISOString()
                ]);
            } else {
                throw new \Exception('Backup creation failed');
            }
            
        } catch (\Exception $e) {
            Log::error('Database backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Database backup failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get queue statistics.
     */
    public function getQueueStats(): JsonResponse
    {
        try {
            $stats = [
                'pending' => $this->getQueueCount(),
                'processing' => DB::table('jobs')->where('reserved_at', '>', 0)->count(),
                'failed' => DB::table('failed_jobs')->count(),
                'completed_today' => $this->getProcessedToday(),
                'total_processed' => Payslip::whereNotNull('processing_completed_at')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get queue stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve queue statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cleanup temporary files.
     */
    private function cleanupTempFiles($olderThan): array
    {
        $deletedCount = 0;
        $freedSpace = 0;
        
        try {
            $tempPath = storage_path('temp');
            if (is_dir($tempPath)) {
                $files = glob($tempPath . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < $olderThan->timestamp) {
                        $fileSize = filesize($file);
                        if (unlink($file)) {
                            $deletedCount++;
                            $freedSpace += $fileSize;
                        }
                    }
                }
            }
            
            // Clean Laravel temp files
            $tmpFiles = glob(sys_get_temp_dir() . '/laravel_*');
            foreach ($tmpFiles as $file) {
                if (is_file($file) && filemtime($file) < $olderThan->timestamp) {
                    $fileSize = filesize($file);
                    if (unlink($file)) {
                        $deletedCount++;
                        $freedSpace += $fileSize;
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::warning('Error during temp file cleanup: ' . $e->getMessage());
        }
        
        return [
            'deleted_count' => $deletedCount,
            'freed_space' => $freedSpace
        ];
    }

    /**
     * Cleanup old log files.
     */
    private function cleanupLogs($olderThan): array
    {
        $deletedCount = 0;
        $freedSpace = 0;
        
        try {
            $logFiles = Storage::disk('local')->files('logs');
            
            foreach ($logFiles as $logFile) {
                $fullPath = storage_path('app/' . $logFile);
                if (filemtime($fullPath) < $olderThan->timestamp) {
                    $fileSize = Storage::disk('local')->size($logFile);
                    if (Storage::disk('local')->delete($logFile)) {
                        $deletedCount++;
                        $freedSpace += $fileSize;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error during log cleanup: ' . $e->getMessage());
        }
        
        return [
            'deleted_count' => $deletedCount,
            'freed_space' => $freedSpace
        ];
    }

    /**
     * Cleanup old payslips.
     */
    private function cleanupOldPayslips($olderThan): array
    {
        $deletedCount = 0;
        $freedSpace = 0;
        
        try {
            $oldPayslips = Payslip::where('created_at', '<', $olderThan)
                                  ->where('status', 'completed')
                                  ->get();
            
            foreach ($oldPayslips as $payslip) {
                if ($payslip->file_path && Storage::exists($payslip->file_path)) {
                    $fileSize = Storage::size($payslip->file_path);
                    Storage::delete($payslip->file_path);
                    $freedSpace += $fileSize;
                }
                
                $payslip->delete();
                $deletedCount++;
            }
        } catch (\Exception $e) {
            Log::warning('Error during payslip cleanup: ' . $e->getMessage());
        }
        
        return [
            'deleted_count' => $deletedCount,
            'freed_space' => $freedSpace
        ];
    }
} 