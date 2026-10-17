<?php

namespace LaravelOps\Connector\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EnrollmentController extends Controller
{
    /**
     * Handle enrollment request from Laravel Ops.
     */
    public function enroll(Request $request): JsonResponse
    {
        // Validate enrollment token
        $token = $request->input('enrollment_token');
        
        if (!$token) {
            return response()->json([
                'error' => 'Enrollment token is required',
            ], 400);
        }

        // Validate token with Laravel Ops central server
        // This would make an HTTP request to Laravel Ops to validate the token
        // For now, we'll implement a simple token validation
        
        $validationResult = $this->validateTokenWithCentral($token, $request);
        
        if (!$validationResult['valid']) {
            return response()->json([
                'error' => 'Invalid or expired enrollment token',
                'message' => $validationResult['message'] ?? 'Token validation failed',
            ], 401);
        }

        // Extract credentials from validation response
        $apiKey = $validationResult['api_key'];
        $apiSecret = $validationResult['api_secret'];

        // Store credentials in .env or config
        // Note: In production, you'd update the .env file or use a secure credential store
        $this->storeCredentials($apiKey, $apiSecret);

        // Clear any cached credentials
        Cache::forget('connector_credentials');

        return response()->json([
            'success' => true,
            'message' => 'Connector enrolled successfully',
            'api_key' => $apiKey,
            'application_id' => $validationResult['application_id'] ?? null,
        ]);
    }

    /**
     * Validate enrollment token with Laravel Ops central server.
     */
    protected function validateTokenWithCentral(string $token, Request $request): array
    {
        // Get the central server URL from the request or config
        $centralUrl = $request->input('central_url', config('connector.central_url'));
        
        if (!$centralUrl) {
            return [
                'valid' => false,
                'message' => 'Central server URL not configured',
            ];
        }

        // Make HTTP request to Laravel Ops to validate token
        try {
            $client = new \GuzzleHttp\Client();
            
            $response = $client->post($centralUrl . '/api/enrollment/validate', [
                'json' => [
                    'token' => $token,
                    'app_url' => url('/'),
                    'app_name' => config('app.name'),
                    'laravel_version' => app()->version(),
                    'php_version' => PHP_VERSION,
                ],
                'timeout' => 10,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() === 200 && isset($body['valid']) && $body['valid']) {
                return $body;
            }

            return [
                'valid' => false,
                'message' => $body['message'] ?? 'Token validation failed',
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Failed to connect to central server: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Store credentials (simplified - in production, update .env file).
     */
    protected function storeCredentials(string $apiKey, string $apiSecret): void
    {
        // In a real implementation, you would:
        // 1. Update the .env file with new credentials
        // 2. Or use a secure credential store
        // 3. Or write to a config file
        
        // For this implementation, we'll use environment variables
        // The user would need to manually update .env or we'd use file_put_contents
        
        // Example (commented out for safety):
        // $envPath = base_path('.env');
        // $envContent = file_get_contents($envPath);
        // $envContent = preg_replace(
        //     '/^CONNECTOR_API_KEY=.*$/m',
        //     'CONNECTOR_API_KEY=' . $apiKey,
        //     $envContent
        // );
        // $envContent = preg_replace(
        //     '/^CONNECTOR_API_SECRET=.*$/m',
        //     'CONNECTOR_API_SECRET=' . $apiSecret,
        //     $envContent
        // );
        // file_put_contents($envPath, $envContent);

        // For now, just log it (in production, this would be written to .env)
        \Log::info('Connector: Credentials received', [
            'api_key' => substr($apiKey, 0, 10) . '...',
        ]);
    }
}
