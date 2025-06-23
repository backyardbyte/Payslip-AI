<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimitMiddleware
{
    protected SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if rate limiting is enabled
        $enableRateLimit = $this->settingsService->get('api.enable_rate_limiting', true);
        
        if (!$enableRateLimit) {
            return $next($request);
        }

        // Get rate limit settings
        $perMinuteLimit = $this->settingsService->get('api.rate_limit_per_minute', 60);
        $perHourLimit = $this->settingsService->get('api.rate_limit_per_hour', 1000);

        // Determine the key for rate limiting (IP or user-based)
        $identifier = $this->getIdentifier($request);

        // Check minute-based limit
        $minuteKey = "api_rate_limit:minute:{$identifier}";
        $minuteRequests = Cache::get($minuteKey, 0);

        if ($minuteRequests >= $perMinuteLimit) {
            Log::warning('API rate limit exceeded (per minute)', [
                'identifier' => $identifier,
                'requests' => $minuteRequests,
                'limit' => $perMinuteLimit,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => "Too many requests. Maximum {$perMinuteLimit} requests per minute allowed.",
                'retry_after' => 60
            ], 429);
        }

        // Check hour-based limit
        $hourKey = "api_rate_limit:hour:{$identifier}";
        $hourRequests = Cache::get($hourKey, 0);

        if ($hourRequests >= $perHourLimit) {
            Log::warning('API rate limit exceeded (per hour)', [
                'identifier' => $identifier,
                'requests' => $hourRequests,
                'limit' => $perHourLimit,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => "Too many requests. Maximum {$perHourLimit} requests per hour allowed.",
                'retry_after' => 3600
            ], 429);
        }

        // Increment counters
        Cache::put($minuteKey, $minuteRequests + 1, 60); // 1 minute TTL
        Cache::put($hourKey, $hourRequests + 1, 3600); // 1 hour TTL

        // Add rate limit headers to response
        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit-Minute', $perMinuteLimit);
        $response->headers->set('X-RateLimit-Remaining-Minute', max(0, $perMinuteLimit - $minuteRequests - 1));
        $response->headers->set('X-RateLimit-Limit-Hour', $perHourLimit);
        $response->headers->set('X-RateLimit-Remaining-Hour', max(0, $perHourLimit - $hourRequests - 1));

        return $response;
    }

    /**
     * Get the identifier for rate limiting.
     */
    protected function getIdentifier(Request $request): string
    {
        // Use authenticated user ID if available, otherwise use IP
        if ($request->user()) {
            return 'user:' . $request->user()->id;
        }

        return 'ip:' . $request->ip();
    }
} 