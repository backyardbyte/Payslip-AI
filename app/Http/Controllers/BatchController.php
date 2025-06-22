<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBatch;
use App\Models\BatchOperation;
use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class BatchController extends ApiResponseController
{
    public function __construct()
    {
        $this->middleware('permission:payslip.create')->only(['uploadBatch', 'createBatch']);
        $this->middleware('permission:payslip.view')->only(['index', 'show', 'status']);
        $this->middleware('permission:payslip.delete')->only(['cancel', 'destroy']);
    }

    /**
     * Get user's batch operations.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $perPage = $request->get('per_page', 15);
        $status = $request->get('status');

        $query = BatchOperation::forUser($userId)
            ->with(['payslips' => function($query) {
                $query->select('id', 'batch_id', 'status', 'file_path', 'created_at');
            }])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $batches = $query->paginate($perPage);

        return $this->successResponse($batches->map(function ($batch) {
            return [
                'id' => $batch->id,
                'batch_id' => $batch->batch_id,
                'name' => $batch->name,
                'status' => $batch->status,
                'total_files' => $batch->total_files,
                'processed_files' => $batch->processed_files,
                'successful_files' => $batch->successful_files,
                'failed_files' => $batch->failed_files,
                'progress_percentage' => $batch->progress_percentage,
                'success_rate' => $batch->success_rate,
                'estimated_completion' => $batch->estimated_completion,
                'started_at' => $batch->started_at?->toIso8601String(),
                'completed_at' => $batch->completed_at?->toIso8601String(),
                'created_at' => $batch->created_at->toIso8601String(),
                'payslips_preview' => $batch->payslips->take(5)->map(function ($payslip) {
                    return [
                        'id' => $payslip->id,
                        'name' => basename($payslip->file_path),
                        'status' => $payslip->status,
                    ];
                }),
            ];
        }));
    }

    /**
     * Get specific batch operation details.
     */
    public function show(BatchOperation $batch): JsonResponse
    {
        // Check ownership
        if ($batch->user_id !== Auth::id() && !Auth::user()->hasPermission('payslip.view_all')) {
            return $this->forbiddenResponse('You can only view your own batch operations.');
        }

        $batch->load(['payslips' => function($query) {
            $query->select('id', 'batch_id', 'status', 'file_path', 'extracted_data', 'processing_error', 'created_at', 'processing_started_at', 'processing_completed_at');
        }]);

        return $this->successResponse([
            'id' => $batch->id,
            'batch_id' => $batch->batch_id,
            'name' => $batch->name,
            'status' => $batch->status,
            'total_files' => $batch->total_files,
            'processed_files' => $batch->processed_files,
            'successful_files' => $batch->successful_files,
            'failed_files' => $batch->failed_files,
            'progress_percentage' => $batch->progress_percentage,
            'success_rate' => $batch->success_rate,
            'estimated_completion' => $batch->estimated_completion,
            'settings' => $batch->settings,
            'metadata' => $batch->metadata,
            'started_at' => $batch->started_at?->toIso8601String(),
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'created_at' => $batch->created_at->toIso8601String(),
            'error_message' => $batch->error_message,
            'payslips' => $batch->payslips->map(function ($payslip) {
                return [
                    'id' => $payslip->id,
                    'name' => basename($payslip->file_path),
                    'status' => $payslip->status,
                    'size' => Storage::exists($payslip->file_path) ? Storage::size($payslip->file_path) : 0,
                    'data' => $payslip->extracted_data,
                    'error' => $payslip->processing_error,
                    'created_at' => $payslip->created_at->toIso8601String(),
                    'processing_started_at' => $payslip->processing_started_at?->toIso8601String(),
                    'processing_completed_at' => $payslip->processing_completed_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Upload multiple files as a batch.
     */
    public function uploadBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array|min:2|max:50', // Batch must have 2-50 files
            'files.*' => [
                'required',
                'file',
                'mimes:pdf,png,jpg,jpeg',
                'max:5120', // 5MB per file
                // Removed dimensions validation as it was too restrictive for payslip images
            ],
            'batch_name' => 'nullable|string|max:255',
            'settings' => 'nullable|array',
            'settings.parallel_processing' => 'nullable|boolean',
            'settings.max_concurrent' => 'nullable|integer|min:1|max:10',
            'settings.priority' => 'nullable|in:low,normal,high',
            'settings.processing_priority' => 'nullable|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $files = $request->file('files');
        $batchName = $request->get('batch_name', 'Batch ' . now()->format('Y-m-d H:i:s'));
        $settings = $request->get('settings', []);

        // Default settings
        $settings = array_merge([
            'parallel_processing' => true,
            'max_concurrent' => 5,
            'priority' => 'normal',
            'processing_priority' => 0,
        ], $settings);

        try {
            DB::beginTransaction();

            // Create batch operation
            $batch = BatchOperation::create([
                'user_id' => Auth::id(),
                'name' => $batchName,
                'status' => 'pending',
                'total_files' => count($files),
                'settings' => $settings,
                'metadata' => [
                    'upload_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ]);

            $payslips = [];
            $uploadErrors = [];

            // Process each file
            foreach ($files as $index => $file) {
                try {
                    // Additional security checks
                    $allowedMimeTypes = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg'];
                    
                    if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
                        $uploadErrors[] = "File {$index}: Invalid file type detected.";
                        continue;
                    }

                    if ($file->getSize() > 5242880) { // 5MB
                        $uploadErrors[] = "File {$index}: File size exceeds maximum allowed size.";
                        continue;
                    }

                    // Store file
                    $path = $file->store('payslips');

                    // Create payslip record
                    $payslip = Payslip::create([
                        'user_id' => Auth::id(),
                        'batch_id' => $batch->batch_id,
                        'file_path' => $path,
                        'status' => 'queued',
                        'processing_priority' => $settings['processing_priority'],
                    ]);

                    $payslips[] = $payslip;

                } catch (\Exception $e) {
                    $uploadErrors[] = "File {$index}: " . $e->getMessage();
                }
            }

            // Update batch with actual file count
            $batch->update([
                'total_files' => count($payslips),
                'metadata' => array_merge($batch->metadata ?? [], [
                    'upload_errors' => $uploadErrors,
                ]),
            ]);

            if (empty($payslips)) {
                DB::rollBack();
                return $this->errorResponse('No files were successfully uploaded.', 422, $uploadErrors);
            }

            // Dispatch batch processing job
            ProcessBatch::dispatch($batch);

            DB::commit();

            return $this->successResponse([
                'batch_id' => $batch->batch_id,
                'batch_name' => $batch->name,
                'total_files' => count($payslips),
                'upload_errors' => $uploadErrors,
                'message' => count($payslips) . ' files uploaded successfully and queued for processing.',
            ], 'Batch upload completed successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return $this->errorResponse(
                'Failed to upload batch: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Create a batch from existing queued payslips.
     */
    public function createBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payslip_ids' => 'required|array|min:2|max:50',
            'payslip_ids.*' => 'required|integer|exists:payslips,id',
            'batch_name' => 'nullable|string|max:255',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $payslipIds = $request->get('payslip_ids');
        $batchName = $request->get('batch_name', 'Batch ' . now()->format('Y-m-d H:i:s'));
        $settings = $request->get('settings', []);

        try {
            DB::beginTransaction();

            // Verify payslips belong to user and are queued
            $payslips = Payslip::whereIn('id', $payslipIds)
                ->where('user_id', Auth::id())
                ->where('status', 'queued')
                ->whereNull('batch_id')
                ->get();

            if ($payslips->count() !== count($payslipIds)) {
                DB::rollBack();
                return $this->errorResponse('Some payslips are not available for batching.', 422);
            }

            // Create batch operation
            $batch = BatchOperation::create([
                'user_id' => Auth::id(),
                'name' => $batchName,
                'status' => 'pending',
                'total_files' => $payslips->count(),
                'settings' => $settings,
            ]);

            // Update payslips with batch_id
            $payslips->each(function ($payslip) use ($batch) {
                $payslip->update(['batch_id' => $batch->batch_id]);
            });

            // Dispatch batch processing job
            ProcessBatch::dispatch($batch);

            DB::commit();

            return $this->successResponse([
                'batch_id' => $batch->batch_id,
                'batch_name' => $batch->name,
                'total_files' => $payslips->count(),
            ], 'Batch created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return $this->errorResponse(
                'Failed to create batch: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get batch processing status.
     */
    public function status(BatchOperation $batch): JsonResponse
    {
        // Check ownership
        if ($batch->user_id !== Auth::id() && !Auth::user()->hasPermission('payslip.view_all')) {
            return $this->forbiddenResponse('You can only view your own batch operations.');
        }

        // Update progress before returning status
        $batch->updateProgress();

        return $this->successResponse([
            'batch_id' => $batch->batch_id,
            'status' => $batch->status,
            'progress_percentage' => $batch->progress_percentage,
            'processed_files' => $batch->processed_files,
            'total_files' => $batch->total_files,
            'successful_files' => $batch->successful_files,
            'failed_files' => $batch->failed_files,
            'estimated_completion' => $batch->estimated_completion,
        ]);
    }

    /**
     * Cancel a batch operation.
     */
    public function cancel(BatchOperation $batch): JsonResponse
    {
        // Check ownership
        if ($batch->user_id !== Auth::id()) {
            return $this->forbiddenResponse('You can only cancel your own batch operations.');
        }

        if ($batch->isCompleted()) {
            return $this->errorResponse('Cannot cancel a completed batch operation.', 422);
        }

        try {
            DB::beginTransaction();

            // Update batch status
            $batch->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);

            // Cancel queued payslips in this batch
            $batch->payslips()
                ->where('status', 'queued')
                ->update(['status' => 'cancelled']);

            DB::commit();

            return $this->successResponse(null, 'Batch operation cancelled successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return $this->errorResponse(
                'Failed to cancel batch: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete a batch operation and its payslips.
     */
    public function destroy(BatchOperation $batch): JsonResponse
    {
        // Check ownership
        if ($batch->user_id !== Auth::id() && !Auth::user()->hasPermission('payslip.view_all')) {
            return $this->forbiddenResponse('You can only delete your own batch operations.');
        }

        try {
            DB::beginTransaction();

            // Delete associated files and payslips
            $payslips = $batch->payslips;
            foreach ($payslips as $payslip) {
                if ($payslip->file_path && Storage::exists($payslip->file_path)) {
                    Storage::delete($payslip->file_path);
                }
                $payslip->delete();
            }

            // Delete batch operation
            $batch->delete();

            DB::commit();

            return $this->successResponse(null, 'Batch operation deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return $this->errorResponse(
                'Failed to delete batch: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get batch processing statistics.
     */
    public function statistics(): JsonResponse
    {
        $userId = Auth::id();

        $stats = [
            'total_batches' => BatchOperation::forUser($userId)->count(),
            'active_batches' => BatchOperation::forUser($userId)->active()->count(),
            'completed_batches' => BatchOperation::forUser($userId)->completed()->count(),
            'total_files_processed' => BatchOperation::forUser($userId)->sum('processed_files'),
            'success_rate' => $this->calculateOverallSuccessRate($userId),
            'recent_batches' => BatchOperation::forUser($userId)
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($batch) {
                    return [
                        'id' => $batch->id,
                        'batch_id' => $batch->batch_id,
                        'name' => $batch->name,
                        'status' => $batch->status,
                        'progress_percentage' => $batch->progress_percentage,
                        'created_at' => $batch->created_at->toIso8601String(),
                    ];
                }),
        ];

        return $this->successResponse($stats);
    }

    /**
     * Calculate overall success rate for user's batches.
     */
    private function calculateOverallSuccessRate(int $userId): float
    {
        $totalProcessed = BatchOperation::forUser($userId)->sum('processed_files');
        $totalSuccessful = BatchOperation::forUser($userId)->sum('successful_files');

        if ($totalProcessed === 0) {
            return 0;
        }

        return round(($totalSuccessful / $totalProcessed) * 100, 2);
    }
} 