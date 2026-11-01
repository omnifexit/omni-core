<?php

namespace Omnifexit\OmniCore\Sso\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Omnifexit\OmniCore\Sso\RevokedUsers;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserNotRevoked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $isRevoked = RevokedUsers::isFlagged($user->id);
        $hasNoRoles = $user->roles()->count() === 0;

        if ($isRevoked || $hasNoRoles || (method_exists($user, 'trashed') && $user->trashed())) {
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($isRevoked) {
                return response()->json([
                    'message' => __('Session revoked.'),
                ], 401);
            }

            return response()->json([
                'message' => __('Access denied: No permission.'),
            ], 403);
        }

        return $next($request);
    }
}
