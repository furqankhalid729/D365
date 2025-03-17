<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $data = $request->getContent();
        $secret = env('SHOPIFY_WEBHOOK_SECRET');
        $calculatedHmac = base64_encode(hash_hmac('sha256', $data, $secret, true));

        if($hmac == "")
            return response()->json(['message' => 'Unauthorized'], 401);
        
        if (!hash_equals($hmac, $calculatedHmac)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
