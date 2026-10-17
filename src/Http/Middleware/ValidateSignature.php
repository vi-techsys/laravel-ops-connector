<?php

namespace LaravelOps\Connector\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaravelOps\Connector\Services\SignatureVerifier;
use Symfony\Component\HttpFoundation\Response;

class ValidateSignature
{
    /**
     * The signature verifier instance.
     */
    protected SignatureVerifier $verifier;

    /**
     * Create a new middleware instance.
     */
    public function __construct(SignatureVerifier $verifier)
    {
        $this->verifier = $verifier;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract signature headers
        $apiKey = $request->header('X-Connector-Key');
        $timestamp = $request->header('X-Connector-Timestamp');
        $nonce = $request->header('X-Connector-Nonce');
        $signature = $request->header('X-Connector-Signature');

        // Check all required headers are present
        if (!$apiKey || !$timestamp || !$nonce || !$signature) {
            return response()->json([
                'error' => 'Missing required authentication headers',
                'required' => [
                    'X-Connector-Key',
                    'X-Connector-Timestamp',
                    'X-Connector-Nonce',
                    'X-Connector-Signature',
                ],
            ], 401);
        }

        // Verify API key matches
        if ($apiKey !== config('connector.api_key')) {
            return response()->json([
                'error' => 'Invalid API key',
            ], 401);
        }

        // Verify timestamp is within tolerance
        if (!$this->verifier->isValidTimestamp($timestamp)) {
            return response()->json([
                'error' => 'Request timestamp is too old or too far in the future',
                'timestamp' => $timestamp,
                'server_time' => now()->toIso8601String(),
            ], 401);
        }

        // Verify nonce hasn't been used (replay protection)
        if (!$this->verifier->isValidNonce($nonce)) {
            return response()->json([
                'error' => 'Request nonce has already been used (possible replay attack)',
            ], 401);
        }

        // Verify signature
        if (!$this->verifier->verify($request, $signature)) {
            return response()->json([
                'error' => 'Invalid request signature',
            ], 401);
        }

        // Mark nonce as used
        $this->verifier->markNonceUsed($nonce);

        return $next($request);
    }
}
