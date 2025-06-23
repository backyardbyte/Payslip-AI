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

    public function index()
    {
        $userId = Auth::id();
        
        // If user has view_all permission, show all payslips, otherwise only their own
        if (Auth::user()->hasPermission('payslip.view_all')) {
            $payslips = Payslip::with('user')->latest()->get();
        } else {
            $payslips = Payslip::where('user_id', $userId)->latest()->get();
        }

        return response()->json($payslips->map(function (Payslip $p) {
            return [
                'id' => $p->id,
                'job_id' => $p->id,
                'name' => basename($p->file_path),
                'size' => $p->file_path ? Storage::size($p->file_path) : 0,
                'status' => $p->status,
                'data' => $p->extracted_data,
                'created_at' => $p->created_at->toIso8601String(),
                'user' => $p->user ? [
                    'id' => $p->user->id,
                    'name' => $p->user->name,
                    'email' => $p->user->email,
                ] : null,
            ];
        }));
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
            return [
                'id' => $p->id,
                'job_id' => $p->id,
                'name' => basename($p->file_path),
                'size' => $p->file_path ? Storage::size($p->file_path) : 0,
                'status' => $p->status,
                'data' => $p->extracted_data,
                'created_at' => $p->created_at->toIso8601String(),
            ];
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
}
