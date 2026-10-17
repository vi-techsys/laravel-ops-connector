<?php

namespace LaravelOps\Connector\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SignatureVerifier
{
    /**
     * Verify the request signature.
     */
    public function verify(Request $request, string $providedSignature): bool
    {
        $secret = config('connector.api_secret');
        
        if (!$secret) {
            Log::error('Connector: API secret not configured');
            return false;
        }

        // Build the string to sign
        $stringToSign = $this->buildStringToSign($request);
        
        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $stringToSign, $secret);
        
        // Use timing-safe comparison
        return hash_equals($expectedSignature, $providedSignature);
    }

    /**
     * Build the string to sign from the request.
     */
    protected function buildStringToSign(Request $request): string
    {
        $method = strtoupper($request->method());
        $path = $request->getPathInfo();
        $timestamp = $request->header('X-Connector-Timestamp');
        $nonce = $request->header('X-Connector-Nonce');
        
        // Hash the request body (if any)
        $bodyHash = '';
        if ($request->getContent()) {
            $bodyHash = hash('sha256', $request->getContent());
        }

        // Format: METHOD|PATH|TIMESTAMP|NONCE|BODY_HASH
        return sprintf('%s|%s|%s|%s|%s', 
            $method, 
            $path, 
            $timestamp, 
            $nonce, 
            $bodyHash
        );
    }

    /**
     * Check if the timestamp is within the allowed tolerance.
     */
    public function isValidTimestamp(string $timestamp): bool
    {
        try {
            $requestTime = new \DateTime($timestamp);
            $serverTime = new \DateTime();
            
            $tolerance = config('connector.timestamp_tolerance', 300);
            $diff = abs($serverTime->getTimestamp() - $requestTime->getTimestamp());
            
            return $diff <= $tolerance;
        } catch (\Exception $e) {
            Log::warning('Connector: Invalid timestamp format', [
                'timestamp' => $timestamp,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if the nonce is valid (not already used).
     */
    public function isValidNonce(string $nonce): bool
    {
        $cacheKey = 'connector_nonce_' . $nonce;
        
        // If nonce exists in cache, it's already been used
        return !Cache::has($cacheKey);
    }

    /**
     * Mark a nonce as used.
     */
    public function markNonceUsed(string $nonce): void
    {
        $cacheKey = 'connector_nonce_' . $nonce;
        $duration = config('connector.nonce_cache_duration', 300);
        
        Cache::put($cacheKey, true, $duration);
    }

    /**
     * Calculate signature for a request (for testing/verification).
     */
    public function calculateSignature(Request $request): string
    {
        $secret = config('connector.api_secret');
        $stringToSign = $this->buildStringToSign($request);
        
        return hash_hmac('sha256', $stringToSign, $secret);
    }
}
