<?php

namespace App\Http\Controllers;

use App\Models\Koperasi;
use App\Models\Payslip;
use App\Models\User;
use App\Jobs\ProcessPayslip;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class TelegramBotController extends ApiResponseController
{
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
                'max_percentage' => $k->rules['max_peratus_gaji_bersih'] ?? null,
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
                'max_percentage_allowed' => $koperasi->rules['max_peratus_gaji_bersih'] ?? null,
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
     * Upload and process payslip via Telegram bot
     */
    public function uploadPayslip(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:pdf,png,jpg,jpeg|max:5120',
            'telegram_user_id' => 'required|string',
            'telegram_username' => 'nullable|string',
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
            $path = $file->store('payslips/telegram');

            // Create payslip record
            $payslip = Payslip::create([
                'user_id' => Auth::id(),
                'file_path' => $path,
                'status' => 'uploaded',
                'processing_priority' => 1,
                'extracted_data' => [
                    'telegram_user_id' => $request->telegram_user_id,
                    'telegram_username' => $request->telegram_username,
                    'uploaded_via' => 'telegram_bot',
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
                'check_status_url' => route('api.telegram.payslip.status', $payslip->id),
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
     * Get user's payslip history
     */
    public function getPayslipHistory(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 10), 50);
        $offset = $request->get('offset', 0);

        $query = Payslip::where('user_id', Auth::id())
            ->latest();

        // Filter by Telegram uploads only
        if ($request->boolean('telegram_only')) {
            $query->whereJsonContains('extracted_data->uploaded_via', 'telegram_bot');
        }

        $payslips = $query->offset($offset)
            ->limit($limit)
            ->get(['id', 'status', 'file_path', 'extracted_data', 'created_at', 'processing_completed_at']);

        return $this->successResponse([
            'payslips' => $payslips->map(function ($p) {
                return [
                    'id' => $p->id,
                    'status' => $p->status,
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
            'telegram_uploads' => Payslip::whereJsonContains('extracted_data->uploaded_via', 'telegram_bot')->count(),
            'system_health' => 'healthy',
        ];

        return $this->successResponse($stats);
    }

    /**
     * Create a new user for Telegram bot (if needed)
     */
    public function createTelegramUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'telegram_user_id' => 'required|string|unique:users,email',
            'telegram_username' => 'nullable|string',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = User::create([
                'name' => trim($request->first_name . ' ' . $request->last_name),
                'email' => $request->telegram_user_id . '@telegram.bot',
                'password' => bcrypt(str()->random(32)),
                'role_id' => 1, // Default role (adjust as needed)
                'is_active' => true,
            ]);

            // Create API token for this user
            $token = $user->createApiToken('telegram_bot', [
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
     * Private method to check koperasi eligibility
     */
    private function checkKoperasiEligibility(array $data, array $rules): array
    {
        $eligible = true;
        $reasons = [];

        // Check maximum percentage of net salary
        if (isset($rules['max_peratus_gaji_bersih'])) {
            if ($data['peratus_gaji_bersih'] > $rules['max_peratus_gaji_bersih']) {
                $eligible = false;
                $reasons[] = "Percentage of net salary ({$data['peratus_gaji_bersih']}%) exceeds maximum allowed ({$rules['max_peratus_gaji_bersih']}%)";
            }
        }

        // Check minimum basic salary
        if (isset($rules['min_gaji_pokok'])) {
            if ($data['gaji_pokok'] < $rules['min_gaji_pokok']) {
                $eligible = false;
                $reasons[] = "Basic salary (RM {$data['gaji_pokok']}) is below minimum required (RM {$rules['min_gaji_pokok']})";
            }
        }

        // Check maximum age
        if (isset($rules['max_umur']) && isset($data['umur'])) {
            if ($data['umur'] > $rules['max_umur']) {
                $eligible = false;
                $reasons[] = "Age ({$data['umur']}) exceeds maximum allowed ({$rules['max_umur']})";
            }
        }

        // Check minimum net salary
        if (isset($rules['min_gaji_bersih'])) {
            if ($data['gaji_bersih'] < $rules['min_gaji_bersih']) {
                $eligible = false;
                $reasons[] = "Net salary (RM {$data['gaji_bersih']}) is below minimum required (RM {$rules['min_gaji_bersih']})";
            }
        }

        if ($eligible) {
            $reasons[] = "Meets all eligibility criteria";
        }

        return [
            'eligible' => $eligible,
            'reasons' => $reasons,
        ];
    }
} 