<?php

namespace App\Http\Controllers;

use App\Models\Koperasi;
use App\Models\Payslip;
use App\Models\User;
use App\Jobs\ProcessPayslip;
use App\Services\WhatsAppBotService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WhatsAppBotController extends ApiResponseController
{
    protected WhatsAppBotService $whatsAppService;
    protected SettingsService $settingsService;

    public function __construct(WhatsAppBotService $whatsAppService, SettingsService $settingsService)
    {
        $this->whatsAppService = $whatsAppService;
        $this->settingsService = $settingsService;
        $this->middleware('permission:whatsapp.manage')->only(['setWebhook', 'deleteWebhook']);
        $this->middleware('permission:whatsapp.view')->only(['getWebhookInfo', 'getBotInfo', 'sendMessage']);
    }

    /**
     * Get all active koperasi with their eligibility rules
     */
    public function getKoperasiList(): JsonResponse
    {
        $koperasi = Koperasi::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rules', 'updated_at']);

        return $this->successResponse($koperasi->map(function ($k) {
            return [
                'id' => $k->id,
                'name' => $k->name,
                'rules' => $k->rules,
                'updated_at' => $k->updated_at->toISOString(),
                'min_take_home_percentage' => $k->rules['min_peratus_gaji_bersih'] ?? null,
                'max_debt_service_ratio' => $k->rules['max_debt_service_ratio'] ?? null,
                'min_salary' => $k->rules['min_gaji_pokok'] ?? null,
                'max_age' => $k->rules['max_umur'] ?? null,
            ];
        }));
    }

    /**
     * Get specific koperasi details
     */
    public function getKoperasi($id): JsonResponse
    {
        $koperasi = Koperasi::where('is_active', true)->find($id);

        if (!$koperasi) {
            return $this->notFoundResponse('Koperasi not found');
        }

        return $this->successResponse([
            'id' => $koperasi->id,
            'name' => $koperasi->name,
            'rules' => $koperasi->rules,
            'is_active' => $koperasi->is_active,
            'created_at' => $koperasi->created_at->toISOString(),
            'updated_at' => $koperasi->updated_at->toISOString(),
        ]);
    }

    /**
     * Check eligibility for multiple koperasi based on salary data
     */
    public function checkEligibility(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gaji_bersih' => 'required|numeric|min:0',
            'gaji_pokok' => 'required|numeric|min:0',
            'peratus_gaji_bersih' => 'required|numeric|min:0|max:100',
            'umur' => 'nullable|integer|min:18|max:80',
            'koperasi_ids' => 'nullable|array',
            'koperasi_ids.*' => 'integer|exists:koperasi,id',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        $data = $request->validated();
        
        // Get koperasi to check
        $query = Koperasi::where('is_active', true);
        if (isset($data['koperasi_ids']) && !empty($data['koperasi_ids'])) {
            $query->whereIn('id', $data['koperasi_ids']);
        }
        $koperasiList = $query->get();

        $results = [];
        foreach ($koperasiList as $koperasi) {
            $eligibility = $this->checkKoperasiEligibility($data, $koperasi->rules);
            
            $results[] = [
                'koperasi_id' => $koperasi->id,
                'koperasi_name' => $koperasi->name,
                'is_eligible' => $eligibility['eligible'],
                'reasons' => $eligibility['reasons'],
                'percentage_used' => $data['peratus_gaji_bersih'],
                'min_take_home_required' => $koperasi->rules['min_peratus_gaji_bersih'] ?? null,
                'max_debt_service_ratio' => $koperasi->rules['max_debt_service_ratio'] ?? null,
            ];
        }

        return $this->successResponse([
            'input_data' => $data,
            'eligibility_results' => $results,
            'total_eligible' => collect($results)->where('is_eligible', true)->count(),
            'total_checked' => count($results),
        ]);
    }

    /**
     * Upload and process payslip via WhatsApp bot
     */
    public function uploadPayslip(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf,png,jpg,jpeg|max:16384', // 16MB for WhatsApp
            'whatsapp_phone' => 'required|string',
            'whatsapp_name' => 'nullable|string',
            'check_koperasi' => 'nullable|boolean',
            'koperasi_ids' => 'nullable|array',
            'koperasi_ids.*' => 'integer|exists:koperasi,id',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            // Store the file
            $file = $request->file('file');
            $path = $file->store('payslips/whatsapp');

            // Create payslip record
            $payslip = Payslip::create([
                'user_id' => Auth::id(),
                'file_path' => $path,
                'status' => 'uploaded',
                'source' => 'whatsapp',
                'processing_priority' => 1,
                'whatsapp_phone' => $request->whatsapp_phone,
                'extracted_data' => [
                    'whatsapp_phone' => $request->whatsapp_phone,
                    'whatsapp_name' => $request->whatsapp_name,
                    'uploaded_via' => 'whatsapp_bot',
                    'check_koperasi' => $request->boolean('check_koperasi', true),
                    'koperasi_ids' => $request->koperasi_ids,
                ],
            ]);

            // Process the payslip
            ProcessPayslip::dispatch($payslip);

            return $this->successResponse([
                'payslip_id' => $payslip->id,
                'status' => 'processing',
                'message' => 'Payslip uploaded successfully and processing started',
                'check_status_url' => route('api.whatsapp.payslip.status', $payslip->id),
            ], 'Payslip uploaded successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to upload payslip: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get payslip processing status
     */
    public function getPayslipStatus($id): JsonResponse
    {
        $payslip = Payslip::find($id);

        if (!$payslip) {
            return $this->notFoundResponse('Payslip not found');
        }

        // Check if user can access this payslip
        if ($payslip->user_id !== Auth::id() && !Auth::user()->hasPermission('payslip.view_all')) {
            return $this->forbiddenResponse('Access denied');
        }

        $response = [
            'id' => $payslip->id,
            'status' => $payslip->status,
            'source' => $payslip->source,
            'file_name' => basename($payslip->file_path),
            'created_at' => $payslip->created_at->toISOString(),
            'processing_started_at' => $payslip->processing_started_at?->toISOString(),
            'processing_completed_at' => $payslip->processing_completed_at?->toISOString(),
        ];

        if ($payslip->status === 'failed') {
            $response['error'] = $payslip->processing_error;
        }

        if ($payslip->status === 'completed' && $payslip->extracted_data) {
            $response['extracted_data'] = $payslip->extracted_data;
            
            // If koperasi check was requested, include eligibility results
            if (isset($payslip->extracted_data['koperasi_results'])) {
                $response['koperasi_eligibility'] = $payslip->extracted_data['koperasi_results'];
            }
        }

        return $this->successResponse($response);
    }

    /**
     * Get payslip processing history
     */
    public function getPayslipHistory(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 10), 50);
        $offset = $request->get('offset', 0);

        $query = Payslip::where('user_id', Auth::id())
            ->latest();

        // Filter by WhatsApp uploads only
        if ($request->boolean('whatsapp_only')) {
            $query->where('source', 'whatsapp');
        }

        $payslips = $query->offset($offset)
            ->limit($limit)
            ->get(['id', 'status', 'source', 'file_path', 'extracted_data', 'created_at', 'processing_completed_at']);

        return $this->successResponse([
            'payslips' => $payslips->map(function ($p) {
                return [
                    'id' => $p->id,
                    'status' => $p->status,
                    'source' => $p->source,
                    'file_name' => basename($p->file_path),
                    'created_at' => $p->created_at->toISOString(),
                    'completed_at' => $p->processing_completed_at?->toISOString(),
                    'has_koperasi_results' => isset($p->extracted_data['koperasi_results']),
                ];
            }),
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $payslips->count() === $limit,
            ],
        ]);
    }

    /**
     * Get system statistics
     */
    public function getStats(): JsonResponse
    {
        $stats = [
            'total_koperasi' => Koperasi::where('is_active', true)->count(),
            'total_payslips_processed' => Payslip::where('status', 'completed')->count(),
            'whatsapp_uploads' => Payslip::where('source', 'whatsapp')->count(),
            'system_health' => 'healthy',
        ];

        return $this->successResponse($stats);
    }

    /**
     * Create a new user for WhatsApp bot (if needed)
     */
    public function createWhatsAppUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'whatsapp_phone' => 'required|string|unique:users,whatsapp_phone',
            'whatsapp_name' => 'nullable|string',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->whatsapp_phone . '@whatsapp.bot',
                'password' => bcrypt(str()->random(32)),
                'role_id' => 1, // Default role (adjust as needed)
                'is_active' => true,
                'whatsapp_phone' => $request->whatsapp_phone,
                'whatsapp_name' => $request->whatsapp_name,
            ]);

            // Create API token for this user
            $token = $user->createApiToken('whatsapp_bot', [
                'koperasi.view',
                'payslip.create',
                'payslip.view',
            ]);

            return $this->successResponse([
                'user_id' => $user->id,
                'api_token' => $token,
                'message' => 'User created successfully',
            ], 'User created successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Private method to check koperasi eligibility based only on peratus gaji bersih
     */
    private function checkKoperasiEligibility(array $data, array $rules): array
    {
        $eligible = true;
        $reasons = [];
        $percentage = $data['peratus_gaji_bersih'];

        // Check minimum take-home percentage requirement (only criteria)
        if (isset($rules['min_peratus_gaji_bersih'])) {
            if ($percentage >= $rules['min_peratus_gaji_bersih']) {
                $eligible = true;
                $reasons[] = "✅ LAYAK: Peratus gaji bersih ({$percentage}%) memenuhi keperluan minimum ({$rules['min_peratus_gaji_bersih']}%)";
                
                // Add financial standing assessment
                if ($percentage >= 85) {
                    $reasons[] = "🌟 Kedudukan kewangan cemerlang";
                } elseif ($percentage >= 75) {
                    $reasons[] = "⭐ Kedudukan kewangan sangat baik";
                } elseif ($percentage >= 65) {
                    $reasons[] = "👍 Kedudukan kewangan baik";
                } else {
                    $reasons[] = "✓ Kedudukan kewangan diterima";
                }
            } else {
                $eligible = false;
                $reasons[] = "❌ TIDAK LAYAK: Peratus gaji bersih ({$percentage}%) kurang dari keperluan minimum ({$rules['min_peratus_gaji_bersih']}%)";
                $reasons[] = "💡 Anda perlu sekurang-kurangnya {$rules['min_peratus_gaji_bersih']}% gaji bersih untuk layak";
            }
        } else {
            $eligible = false;
            $reasons[] = "❌ Tiada kriteria kelayakan ditetapkan untuk koperasi ini";
        }

        return [
            'eligible' => $eligible,
            'reasons' => $reasons
        ];
    }

    public function processFileUpload(Request $request): JsonResponse
    {
        try {
            // Get settings
            $maxFileSizeMB = $this->settingsService->get('general.max_file_size', 5);
            $maxFileSizeKB = $maxFileSizeMB * 1024;
            // WhatsApp typically supports larger files, so we'll use a higher limit but still respect settings
            $whatsAppMaxSize = max($maxFileSizeKB, 16384); // At least 16MB for WhatsApp
            $allowedFileTypes = $this->settingsService->get('general.allowed_file_types', ['pdf', 'png', 'jpg', 'jpeg']);

            $validator = Validator::make($request->all(), [
                'file' => [
                    'required',
                    'file',
                    'mimes:' . implode(',', $allowedFileTypes),
                    'max:' . $whatsAppMaxSize
                ],
                'phone_number' => 'required|string',
                'user_id' => 'nullable|integer|exists:users,id',
                'message_id' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // ... existing code ...

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process file upload: ' . $e->getMessage(), 500);
        }
    }
} 