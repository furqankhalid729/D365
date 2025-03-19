<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class TokenController extends Controller
{
    public function getToken(Request $request)
    {
        $secretKey = $request->header('secret_key');
        if (empty($secretKey)) {
            return response()->json(['message' => 'Secret key is required'], 400);
        }
        if ($secretKey !== env('API_SECRET_KEY')) {
            return response()->json(['message' => 'Invalid secret key'], 403);
        }
        $token = Str::random(90);
        Cache::put("api_token:$token", true, now()->addHour());

        return response()->json(['token' => $token]);
    }
}
