<?php

namespace App\Http\Controllers;

use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    protected SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
        // Temporarily disable permission middleware for testing
        // $this->middleware('permission:system.manage_settings');
    }

    /**
     * Get all settings grouped by category.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $environment = $request->get('environment', config('app.env'));
            $settings = $this->settingsService->getAllSettings($environment);
            $categories = $this->settingsService->getSettingCategories();

            return response()->json([
                'success' => true,
                'data' => [
                    'settings' => $settings,
                    'categories' => $categories,
                    'environment' => $environment
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get settings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update multiple settings.
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'settings' => 'required|array',
                'environment' => 'sometimes|string|in:local,development,staging,production'
            ]);

            $environment = $request->get('environment', config('app.env'));
            $settings = $request->get('settings');

            $result = $this->settingsService->setMultiple($settings, $environment);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Settings updated successfully',
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Some settings failed to update',
                    'data' => $result
                ], 422);
            }

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to update settings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific setting.
     */
    public function show(string $key, Request $request): JsonResponse
    {
        try {
            $environment = $request->get('environment', config('app.env'));
            $value = $this->settingsService->get($key, null, $environment);

            if ($value === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setting not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'key' => $key,
                    'value' => $value,
                    'environment' => $environment
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get setting {$key}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a specific setting.
     */
    public function updateSetting(string $key, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'value' => 'required',
                'environment' => 'sometimes|string|in:local,development,staging,production'
            ]);

            $environment = $request->get('environment', config('app.env'));
            $value = $request->get('value');

            $this->settingsService->set($key, $value, $environment);

            return response()->json([
                'success' => true,
                'message' => 'Setting updated successfully',
                'data' => [
                    'key' => $key,
                    'value' => $value,
                    'environment' => $environment
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error("Failed to update setting {$key}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset a setting to its default value.
     */
    public function reset(string $key, Request $request): JsonResponse
    {
        try {
            $environment = $request->get('environment', config('app.env'));
            
            $this->settingsService->resetToDefault($key, $environment);

            return response()->json([
                'success' => true,
                'message' => 'Setting reset to default successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to reset setting {$key}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset all settings to their default values.
     */
    public function resetAll(Request $request): JsonResponse
    {
        try {
            $environment = $request->get('environment', config('app.env'));
            
            $this->settingsService->resetAllToDefaults($environment);

            return response()->json([
                'success' => true,
                'message' => 'All settings reset to defaults successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reset all settings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get setting history.
     */
    public function history(string $key, Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 50);
            $history = $this->settingsService->getSettingHistory($key, $limit);

            return response()->json([
                'success' => true,
                'data' => $history
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get setting history for {$key}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve setting history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear settings cache.
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->settingsService->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Settings cache cleared successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear settings cache: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export settings.
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $environment = $request->get('environment', config('app.env'));
            $data = $this->settingsService->exportSettings($environment);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to export settings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import settings.
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'settings_data' => 'required|array',
                'environment' => 'sometimes|string|in:local,development,staging,production'
            ]);

            $environment = $request->get('environment', config('app.env'));
            $settingsData = $request->get('settings_data');

            $results = $this->settingsService->importSettings($settingsData, $environment);

            return response()->json([
                'success' => true,
                'message' => 'Settings imported successfully',
                'data' => $results
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to import settings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to import settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 