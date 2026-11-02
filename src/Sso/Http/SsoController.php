<?php

namespace Omnifexit\OmniCore\Sso\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class SsoController
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('omniauth')->redirect();
    }

    public function callback(): RedirectResponse
    {
        /** @var SocialiteUser $omniAuthUser */
        $omniAuthUser = Socialite::driver('omniauth')->user();
        /** @var list<array{name: string, team_id?: string|null}> $roles */
        $roles = $omniAuthUser->user['roles'] ?? [];

        $clientId = (string) config('services.omniauth.client_id');
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        $roleNames = collect($roles)
            ->filter(fn ($role) => ($role['team_id'] ?? null) === $clientId)
            ->pluck('name')
            ->toArray();

        if (empty($roleNames)) {
            return redirect()->to($frontendUrl.'/login?error=access_denied');
        }

        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        $user = $userModel::withTrashed()
            ->where('omniauth_id', $omniAuthUser->getId())
            ->orWhere('email', $omniAuthUser->getEmail())
            ->first();

        if (! $user) {
            $user = new $userModel;
        }

        $user->fill([
            'phone_number' => $omniAuthUser->user['phone_number'] ?? null,
            'omniauth_id' => $omniAuthUser->getId(),
            'email' => $omniAuthUser->getEmail(),
            'name' => $omniAuthUser->getName(),
            'email_verified_at' => now(),
        ]);

        if (method_exists($user, 'trashed') && $user->trashed()) {
            $user->restore();
        }

        $user->save();

        foreach ($roleNames as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        try {
            $user->syncRoles($roleNames);
        } catch (UniqueConstraintViolationException) {
        }

        Auth::login($user, true);

        return redirect()->to($frontendUrl);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
