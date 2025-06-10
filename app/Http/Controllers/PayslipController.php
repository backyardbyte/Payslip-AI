<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPayslip;
use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PayslipController extends Controller
{
    public function index()
    {
        // For now, assume user_id 1. In a real app, you'd use Auth::id()
        $payslips = Payslip::where('user_id', Auth::id() ?? 1)
            ->latest()
            ->get();

        return response()->json($payslips->map(function (Payslip $p) {
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
     * Get current processing queue (for Dashboard)
     * Only shows recent uploads and active processing items
     */
    public function queue()
    {
        $userId = Auth::id() ?? 1;
        
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
        $userId = Auth::id() ?? 1;
        
        // Only clear completed/failed items from the last 24 hours (not permanent history)
        $payslips = Payslip::where('user_id', $userId)
            ->where(function($query) {
                $query->where('status', 'completed')
                      ->where('created_at', '>=', now()->subHours(24))
                      ->orWhere(function($subQuery) {
                          $subQuery->where('status', 'failed')
                                   ->where('created_at', '>=', now()->subHours(24));
                      });
            })
            ->get();
        
        // Delete associated files
        foreach ($payslips as $payslip) {
            if ($payslip->file_path && Storage::exists($payslip->file_path)) {
                Storage::delete($payslip->file_path);
            }
        }
        
        // Delete the payslips
        $deletedCount = Payslip::where('user_id', $userId)
            ->where(function($query) {
                $query->where('status', 'completed')
                      ->where('created_at', '>=', now()->subHours(24))
                      ->orWhere(function($subQuery) {
                          $subQuery->where('status', 'failed')
                                   ->where('created_at', '>=', now()->subHours(24));
                      });
            })
            ->delete();

        return response()->json([
            'message' => "Successfully cleared {$deletedCount} recent items from queue",
            'deleted_count' => $deletedCount
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'],
        ]);

        $path = $request->file('file')->store('payslips');

        $payslip = Payslip::create([
            'user_id' => Auth::id() ?? 1, // Fallback to user 1 if not authenticated for now
            'file_path' => $path,
            'status' => 'queued',
        ]);

        ProcessPayslip::dispatch($payslip);

        return response()->json(['job_id' => $payslip->id]);
    }

    public function status(Payslip $payslip)
    {
        // Add authorization check if needed
        // if ($payslip->user_id !== Auth::id()) {
        //     abort(403);
        // }

        return response()->json([
            'job_id' => $payslip->id,
            'status' => $payslip->status,
            'data' => $payslip->extracted_data,
        ]);
    }

    public function destroy(Payslip $payslip)
    {
        // Add authorization check
        if ($payslip->user_id !== (Auth::id() ?? 1)) {
            abort(403, 'Unauthorized');
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
        $userId = Auth::id() ?? 1;
        
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
