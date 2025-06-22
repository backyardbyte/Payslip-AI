<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $ability = null): Response
    {
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return response()->json([
                'error' => 'API token required',
                'message' => 'Please provide a valid API token in Authorization header'
            ], 401);
        }

        $hashedToken = hash('sha256', $token);
        $apiToken = ApiToken::where('token', $hashedToken)->first();

        if (!$apiToken) {
            return response()->json([
                'error' => 'Invalid token',
                'message' => 'The provided API token is invalid'
            ], 401);
        }

        if ($apiToken->isExpired()) {
            return response()->json([
                'error' => 'Token expired',
                'message' => 'The API token has expired'
            ], 401);
        }

        if (!$apiToken->user->isActive()) {
            return response()->json([
                'error' => 'User inactive',
                'message' => 'The associated user account is inactive'
            ], 401);
        }

        // Check specific ability if provided
        if ($ability && !$apiToken->can($ability)) {
            return response()->json([
                'error' => 'Insufficient permissions',
                'message' => "Token does not have permission: {$ability}"
            ], 403);
        }

        // Set the user for the request
        Auth::setUser($apiToken->user);
        $request->attributes->set('api_token', $apiToken);

        // Update last used timestamp
        $apiToken->updateLastUsed();

        return $next($request);
    }

    /**
     * Get the token from the request.
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        // Check Authorization header
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Check query parameter (for webhook URLs)
        return $request->query('api_token');
    }
} 