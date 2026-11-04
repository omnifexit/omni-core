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
use Omnifexit\OmniCore\Jobs\Auth\LogOutUserJob;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

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

        if ($user->trashed()) {
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
        $omniAuthId = Auth::guard('web')->user()?->getAttribute('omniauth_id');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($omniAuthId) {
            $this->tellOmniAuth((int) $omniAuthId);
        }

        return response()->noContent();
    }

    /**
     * Their OmniAuth session has often expired by now, as using an app doesn't keep it alive, so the browser can't be
     * relied on for this. A failure here mustn't stop someone from logging out.
     */
    private function tellOmniAuth(int $omniAuthId): void
    {
        try {
            LogOutUserJob::dispatch(config('services.omniauth.client_id'), $omniAuthId)
                ->onConnection('redis')
                ->onQueue(LogOutUserJob::queueName());
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
