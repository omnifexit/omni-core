<?php

namespace Omnifexit\OmniCore\Sso\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Omnifexit\OmniCore\Sso\CacheKeys;
use Throwable;

class LauncherController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->omniauth_id) {
            return response()->json(['apps' => [], 'tools' => []]);
        }

        $cacheKey = CacheKeys::launcher($user->omniauth_id);

        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $internalHost = rtrim((string) config('services.omniauth.internal_host', 'http://omnifexit-auth:80'), '/');
        $apiKey = config('app.internal_api_key');

        try {
            $response = Http::withHeaders([
                'X-Internal-API-Key' => $apiKey,
            ])
                ->timeout(2.5)
                ->get("{$internalHost}/api/internal/users/{$user->omniauth_id}/launcher");

            if ($response->successful()) {
                $data = $response->json();
                Cache::put($cacheKey, $data, 300);

                return response()->json($data);
            }

            Log::warning('Launcher fetch failed from OmniAuth', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Launcher fetch exception from OmniAuth: '.$e->getMessage());
        }

        return response()->json(['apps' => [], 'tools' => []]);
    }
}
