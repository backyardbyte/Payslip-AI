<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPayslip;
use App\Models\Payslip;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PayslipController extends Controller
{
    protected SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
        $this->middleware('permission:payslip.view')->only(['index', 'show', 'queue', 'status']);
        $this->middleware('permission:payslip.create')->only(['upload']);
        $this->middleware('permission:payslip.delete')->only(['destroy', 'clearAll', 'clearCompleted', 'clearQueue']);
        $this->middleware('permission:payslip.view_all')->only(['adminIndex']);
    }

    public function index(Request $request)
    {
        $userId = Auth::id();
        $perPage = $request->get('per_page', 20);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $status = $request->get('status');
        $search = $request->get('search');
        
        // Build query
        $query = Payslip::query();
        
        // If user has view_all permission, show all payslips, otherwise only their own
        if (Auth::user()->hasPermission('payslip.view_all')) {
            $query->with('user');
        } else {
            $query->where('user_id', $userId);
        }
        
        // Apply filters
        if ($status) {
            $query->where('status', $status);
        }
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('file_path', 'like', '%' . $search . '%')
                  ->orWhereJsonContains('extracted_data->nama', $search)
                  ->orWhereJsonContains('extracted_data->no_gaji', $search);
            });
        }
        
        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);
        
        // Paginate
        $payslips = $query->paginate($perPage);

        return response()->json([
            'data' => $payslips->items(),
            'meta' => [
                'current_page' => $payslips->currentPage(),
                'last_page' => $payslips->lastPage(),
                'per_page' => $payslips->perPage(),
                'total' => $payslips->total(),
                'from' => $payslips->firstItem(),
                'to' => $payslips->lastItem(),
            ],
            'payslips' => $payslips->getCollection()->map(function (Payslip $p) {
                return $this->formatPayslipResponse($p);
            })
        ]);
    }

    /**
     * Get current processing queue (for Dashboard)
     * Only shows recent uploads and active processing items
     */
    public function queue()
    {
        $userId = Auth::id();
        
        // Get recent payslips that are still in queue/processing or recently completed
        $queuePayslips = Payslip::where('user_id', $userId)
            ->where(function($query) {
                $query->whereIn('status', ['queued', 'processing'])
                      ->orWhere(function($subQuery) {
                          $subQuery->where('status', 'completed')
                                   ->where('created_at', '>=', now()->subHours(24)); // Show completed items from last 24h
                      })
                      ->orWhere(function($subQuery) {
                          $subQuery->where('status', 'failed')
                                   ->where('created_at', '>=', now()->subHours(24)); // Show failed items from last 24h
                      });
            })
            ->latest()
            ->take(50) // Limit queue view
            ->get();

        return response()->json($queuePayslips->map(function (Payslip $p) {
            return $this->formatPayslipResponse($p);
        }));
    }

    /**
     * Clear only items from the processing queue (for Dashboard)
     * This only clears recent completed/failed items, not the permanent history
     */
    public function clearQueue()
    {
        $userId = Auth::id();
        
        // Build the query once for reuse
        $baseQuery = Payslip::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours(24))
            ->whereIn('status', ['completed', 'failed']);
        
        // Get payslips with file paths for deletion
        $payslips = $baseQuery->whereNotNull('file_path')->get(['id', 'file_path']);
        
        // Delete associated files
        foreach ($payslips as $payslip) {
            if (Storage::exists($payslip->file_path)) {
                Storage::delete($payslip->file_path);
            }
        }
        
        // Delete the payslips in a single query
        $deletedCount = Payslip::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHours(24))
            ->whereIn('status', ['completed', 'failed'])
            ->delete();

        return response()->json([
            'message' => "Successfully cleared {$deletedCount} recent items from queue",
            'deleted_count' => $deletedCount
        ]);
    }

    public function upload(Request $request)
    {
        try {
            \Log::info('Upload request received', [
                'user_id' => Auth::id(),
                'has_file' => $request->hasFile('file'),
                'files' => $request->allFiles(),
            ]);

            // Get max file size from settings (in MB)
            $maxFileSizeMB = $this->settingsService->get('general.max_file_size', 5);
            $maxFileSizeKB = $maxFileSizeMB * 1024; // Convert to KB for validation
            $maxFileSizeBytes = $maxFileSizeMB * 1024 * 1024; // Convert to bytes for additional check

            // Get allowed file types from settings
            $allowedFileTypes = $this->settingsService->get('general.allowed_file_types', ['pdf', 'png', 'jpg', 'jpeg']);
            $allowedMimeTypes = [
                'pdf' => 'application/pdf',
                'png' => 'image/png', 
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg'
            ];
            
            $validationRules = [
                'file' => [
                    'required', 
                    'file', 
                    'mimes:' . implode(',', $allowedFileTypes), 
                    'max:' . $maxFileSizeKB,
                ],
            ];

            $request->validate($validationRules);

            \Log::info('Validation passed');

            // Additional security checks
            $file = $request->file('file');
            $allowedMimeTypesArray = array_values(array_intersect_key($allowedMimeTypes, array_flip($allowedFileTypes)));
            
            \Log::info('File details', [
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'max_allowed_size' => $maxFileSizeBytes,
            ]);
            
            if (!in_array($file->getMimeType(), $allowedMimeTypesArray)) {
                \Log::error('Invalid file type', ['mime' => $file->getMimeType()]);
                abort(422, 'Invalid file type detected.');
            }

            // Check file size again with settings value
            if ($file->getSize() > $maxFileSizeBytes) {
                \Log::error('File too large', [
                    'size' => $file->getSize(),
                    'max_allowed' => $maxFileSizeBytes
                ]);
                abort(422, "File size exceeds maximum allowed size of {$maxFileSizeMB}MB.");
            }

            $path = $request->file('file')->store('payslips');
            \Log::info('File stored', ['path' => $path]);

            // Ensure user is authenticated
            if (!Auth::check()) {
                \Log::error('User not authenticated');
                abort(401, 'Authentication required to upload files.');
            }

            \Log::info('Creating payslip record', [
                'user_id' => Auth::id(),
                'file_path' => $path,
            ]);

            $payslip = Payslip::create([
                'user_id' => Auth::id(),
                'file_path' => $path,
                'status' => 'queued',
            ]);

            \Log::info('Payslip created', ['id' => $payslip->id]);

            ProcessPayslip::dispatch($payslip);
            \Log::info('Job dispatched', ['payslip_id' => $payslip->id]);

            return response()->json(['job_id' => $payslip->id]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('Upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    public function status(Payslip $payslip)
    {
        // Check authorization - users can only view their own payslips unless they have view_all permission
        if ($payslip->user_id !== Auth::id() && !Auth::user()->hasPermission('payslip.view_all')) {
            abort(403, 'You can only view your own payslip status.');
        }

        return response()->json([
            'job_id' => $payslip->id,
            'status' => $payslip->status,
            'data' => $payslip->extracted_data,
        ]);
    }

    public function destroy(Payslip $payslip)
    {
        // Check permission and ownership
        if (!Auth::user()->hasPermission('payslip.delete') && 
            !Auth::user()->hasPermission('payslip.view_all')) {
            abort(403, 'You do not have permission to delete payslips.');
        }

        // If user doesn't have view_all permission, check ownership
        if (!Auth::user()->hasPermission('payslip.view_all') && 
            $payslip->user_id !== Auth::id()) {
            abort(403, 'You can only delete your own payslips.');
        }

        // Delete the file from storage
        if ($payslip->file_path && Storage::exists($payslip->file_path)) {
            Storage::delete($payslip->file_path);
        }

        $payslip->delete();

        return response()->json(['message' => 'Payslip deleted successfully']);
    }

    public function clearAll()
    {
        $userId = Auth::id();
        
        // Get all payslips for the user
        $payslips = Payslip::where('user_id', $userId)->get();
        
        // Delete associated files
        foreach ($payslips as $payslip) {
            if ($payslip->file_path && Storage::exists($payslip->file_path)) {
                Storage::delete($payslip->file_path);
            }
        }
        
        // Delete all payslips for the user
        $deletedCount = Payslip::where('user_id', $userId)->delete();

        return response()->json([
            'message' => "Successfully cleared {$deletedCount} payslips from queue",
            'deleted_count' => $deletedCount
        ]);
    }

    public function clearCompleted()
    {
        $userId = Auth::id() ?? 1;
        
        // Get completed payslips for the user
        $payslips = Payslip::where('user_id', $userId)
            ->where('status', 'completed')
            ->get();
        
        // Delete associated files
        foreach ($payslips as $payslip) {
            if ($payslip->file_path && Storage::exists($payslip->file_path)) {
                Storage::delete($payslip->file_path);
            }
        }
        
        // Delete completed payslips
        $deletedCount = Payslip::where('user_id', $userId)
            ->where('status', 'completed')
            ->delete();

        return response()->json([
            'message' => "Successfully cleared {$deletedCount} completed payslips",
            'deleted_count' => $deletedCount
        ]);
    }

    public function statistics()
    {
        $userId = Auth::id() ?? 1;
        
        $stats = [
            'total' => Payslip::where('user_id', $userId)->count(),
            'queued' => Payslip::where('user_id', $userId)->where('status', 'queued')->count(),
            'processing' => Payslip::where('user_id', $userId)->where('status', 'processing')->count(),
            'completed' => Payslip::where('user_id', $userId)->where('status', 'completed')->count(),
            'failed' => Payslip::where('user_id', $userId)->where('status', 'failed')->count(),
        ];

        // Get recent activity
        $recentActivity = Payslip::where('user_id', $userId)
            ->latest()
            ->take(5)
            ->get(['id', 'status', 'created_at', 'file_path'])
            ->map(function ($payslip) {
                return [
                    'id' => $payslip->id,
                    'name' => basename($payslip->file_path),
                    'status' => $payslip->status,
                    'created_at' => $payslip->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'stats' => $stats,
            'recent_activity' => $recentActivity
        ]);
    }

    /**
     * Get detailed payslip analytics
     */
    public function analytics(Request $request)
    {
        $userId = Auth::id();
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days);
        
        $query = Payslip::where('user_id', $userId)
                        ->where('created_at', '>=', $startDate);
        
        $analytics = [
            'total_processed' => $query->whereIn('status', ['completed', 'failed'])->count(),
            'success_rate' => 0,
            'average_processing_time' => 0,
            'most_common_errors' => [],
            'processing_trends' => [],
            'quality_metrics' => [
                'high_confidence' => 0,
                'medium_confidence' => 0,
                'low_confidence' => 0,
            ],
            'data_completeness' => [
                'complete' => 0,
                'partial' => 0,
                'minimal' => 0,
            ]
        ];
        
        $completedPayslips = $query->where('status', 'completed')->get();
        $failedPayslips = $query->where('status', 'failed')->get();
        
        // Calculate success rate
        $totalProcessed = $completedPayslips->count() + $failedPayslips->count();
        if ($totalProcessed > 0) {
            $analytics['success_rate'] = round(($completedPayslips->count() / $totalProcessed) * 100, 2);
        }
        
        // Calculate average processing time
        $processingTimes = $completedPayslips->filter(function($p) {
                return $p->processing_started_at && $p->processing_completed_at;
            })->map(function($p) {
                return $p->processing_started_at->diffInSeconds($p->processing_completed_at);
            });
            
        if ($processingTimes->count() > 0) {
            $analytics['average_processing_time'] = round($processingTimes->avg(), 2);
        }
        
        // Analyze quality metrics
        foreach ($completedPayslips as $payslip) {
            $metadata = $payslip->processing_metadata ?? [];
            $confidence = $metadata['confidence_score'] ?? 0;
            
            if ($confidence >= 80) {
                $analytics['quality_metrics']['high_confidence']++;
            } elseif ($confidence >= 60) {
                $analytics['quality_metrics']['medium_confidence']++;
            } else {
                $analytics['quality_metrics']['low_confidence']++;
            }
            
            // Data completeness analysis
            $extractedData = $payslip->extracted_data ?? [];
            $completeness = $this->calculateDataCompleteness($extractedData);
            
            if ($completeness >= 80) {
                $analytics['data_completeness']['complete']++;
            } elseif ($completeness >= 50) {
                $analytics['data_completeness']['partial']++;
            } else {
                $analytics['data_completeness']['minimal']++;
            }
        }
        
        // Get processing trends (last 7 days)
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Payslip::where('user_id', $userId)
                           ->whereDate('created_at', $date)
                           ->count();
            
            $analytics['processing_trends'][] = [
                'date' => $date->format('Y-m-d'),
                'count' => $count
            ];
        }
        
        // Get most common errors
        $errorCounts = $failedPayslips->groupBy('processing_error')
                                     ->map(function($group) {
                                         return $group->count();
                                     })
                                     ->sortDesc()
                                     ->take(5);
        
        $analytics['most_common_errors'] = $errorCounts->map(function($count, $error) {
            return [
                'error' => substr($error, 0, 100), // Truncate long errors
                'count' => $count
            ];
        })->values();
        
        return response()->json($analytics);
    }

    /**
     * Get enhanced payslip details
     */
    public function show(Payslip $payslip)
    {
        // Check authorization
        if ($payslip->user_id !== Auth::id() && !Auth::user()->hasPermission('payslip.view_all')) {
            abort(403, 'You can only view your own payslips.');
        }

        return response()->json($this->formatDetailedPayslipResponse($payslip));
    }

    /**
     * Reprocess a failed payslip
     */
    public function reprocess(Payslip $payslip)
    {
        // Check authorization
        if ($payslip->user_id !== Auth::id() && !Auth::user()->hasPermission('payslip.view_all')) {
            abort(403, 'You can only reprocess your own payslips.');
        }

        if ($payslip->status !== 'failed') {
            return response()->json(['error' => 'Only failed payslips can be reprocessed'], 422);
        }

        // Reset payslip status and dispatch processing job
        $payslip->update([
            'status' => 'queued',
            'processing_error' => null,
            'processing_started_at' => null,
            'processing_completed_at' => null,
        ]);

        ProcessPayslip::dispatch($payslip);

        return response()->json([
            'message' => 'Payslip queued for reprocessing',
            'payslip_id' => $payslip->id
        ]);
    }

    /**
     * Bulk operations on payslips
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:delete,reprocess,export',
            'payslip_ids' => 'required|array|min:1|max:50',
            'payslip_ids.*' => 'integer|exists:payslips,id'
        ]);

        $userId = Auth::id();
        $action = $request->action;
        $payslipIds = $request->payslip_ids;

        // Get payslips user has permission to modify
        $query = Payslip::whereIn('id', $payslipIds);
        
        if (!Auth::user()->hasPermission('payslip.view_all')) {
            $query->where('user_id', $userId);
        }
        
        $payslips = $query->get();

        if ($payslips->count() !== count($payslipIds)) {
            return response()->json(['error' => 'Some payslips not found or access denied'], 403);
        }

        $results = [];

        switch ($action) {
            case 'delete':
                foreach ($payslips as $payslip) {
                    if ($payslip->file_path && Storage::exists($payslip->file_path)) {
                        Storage::delete($payslip->file_path);
                    }
                    $payslip->delete();
                    $results[] = ['id' => $payslip->id, 'status' => 'deleted'];
                }
                break;

            case 'reprocess':
                foreach ($payslips as $payslip) {
                    if ($payslip->status === 'failed') {
                        $payslip->update([
                            'status' => 'queued',
                            'processing_error' => null,
                            'processing_started_at' => null,
                            'processing_completed_at' => null,
                        ]);
                        ProcessPayslip::dispatch($payslip);
                        $results[] = ['id' => $payslip->id, 'status' => 'queued'];
                    } else {
                        $results[] = ['id' => $payslip->id, 'status' => 'skipped', 'reason' => 'not failed'];
                    }
                }
                break;

            case 'export':
                // TODO: Implement export functionality
                $results = ['export_url' => route('payslips.export', ['ids' => implode(',', $payslipIds)])];
                break;
        }

        return response()->json([
            'message' => "Bulk {$action} completed",
            'results' => $results,
            'processed_count' => count($results)
        ]);
    }

    /**
     * Format standard payslip response
     */
    private function formatPayslipResponse(Payslip $payslip): array
    {
        $rawData = $payslip->extracted_data ?? [];
        
        // Handle nested data structure - extract actual data from nested structure
        $extractedData = $rawData['extracted_data'] ?? $rawData;
        $qualityMetrics = $rawData['quality_metrics'] ?? [];
        $processingMetadata = $rawData['processing_metadata'] ?? [];
        $koperasiResults = $rawData['koperasi_results'] ?? [];
        
        return [
            'id' => $payslip->id,
            'job_id' => $payslip->id,
            'name' => basename($payslip->file_path),
            'size' => $payslip->file_path ? Storage::size($payslip->file_path) : 0,
            'status' => $payslip->status,
            'data' => [
                'nama' => $extractedData['nama'] ?? null,
                'no_gaji' => $extractedData['no_gaji'] ?? null,
                'bulan' => $extractedData['bulan'] ?? null,
                'gaji_pokok' => $extractedData['gaji_pokok'] ?? null,
                'jumlah_pendapatan' => $extractedData['jumlah_pendapatan'] ?? null,
                'jumlah_potongan' => $extractedData['jumlah_potongan'] ?? null,
                'gaji_bersih' => $extractedData['gaji_bersih'] ?? null,
                'peratus_gaji_bersih' => $extractedData['peratus_gaji_bersih'] ?? null,
                'koperasi_results' => $koperasiResults,
                'debug_info' => [
                    'text_length' => $processingMetadata['text_length'] ?? 0,
                    'extraction_patterns_found' => $extractedData['debug_patterns'] ?? [],
                    'confidence_scores' => $extractedData['confidence_scores'] ?? [],
                ],
                'error' => $payslip->processing_error,
            ],
            'quality_metrics' => [
                'confidence_score' => $processingMetadata['confidence_score'] ?? $qualityMetrics['extraction_accuracy'] ?? 0,
                'data_completeness' => $qualityMetrics['data_completeness'] ?? $this->calculateDataCompleteness($extractedData),
                'processing_time' => $processingMetadata['processing_time_seconds'] ?? 0,
            ],
            'koperasi_summary' => [
                'total_checked' => count($koperasiResults),
                'eligible_count' => collect($koperasiResults)->filter()->count(),
            ],
            'created_at' => $payslip->created_at->toIso8601String(),
            'processing_completed_at' => $payslip->processing_completed_at?->toIso8601String(),
            'user' => $payslip->user ? [
                'id' => $payslip->user->id,
                'name' => $payslip->user->name,
                'email' => $payslip->user->email,
            ] : null,
        ];
    }

    /**
     * Format detailed payslip response
     */
    private function formatDetailedPayslipResponse(Payslip $payslip): array
    {
        $base = $this->formatPayslipResponse($payslip);
        $extractedData = $payslip->extracted_data ?? [];
        $processingMetadata = $payslip->processing_metadata ?? [];
        
        return array_merge($base, [
            'full_extracted_data' => $extractedData,
            'processing_metadata' => $processingMetadata,
            'validation_results' => $extractedData['validation_results'] ?? null,
            'detailed_koperasi_results' => $extractedData['detailed_koperasi_results'] ?? [],
            'processing_error' => $payslip->processing_error,
            'file_info' => [
                'path' => $payslip->file_path,
                'mime_type' => Storage::exists($payslip->file_path) ? Storage::mimeType($payslip->file_path) : null,
                'size_formatted' => $this->formatFileSize($payslip->file_path ? Storage::size($payslip->file_path) : 0),
            ]
        ]);
    }

    /**
     * Calculate data completeness percentage
     */
    private function calculateDataCompleteness(array $data): float
    {
        $importantFields = ['gaji_bersih', 'peratus_gaji_bersih', 'gaji_pokok', 'nama', 'no_gaji', 'bulan'];
        $foundFields = 0;
        
        foreach ($importantFields as $field) {
            if (!empty($data[$field])) {
                $foundFields++;
            }
        }
        
        return round(($foundFields / count($importantFields)) * 100, 2);
    }

    /**
     * Format file size
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
