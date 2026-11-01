<?php

namespace Omnifexit\OmniCore\Sso;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

class OmniAuthProvider extends AbstractProvider
{
    /** @var list<string> */
    protected $scopes = ['user:read', 'user:email'];

    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->getPublicBaseUrl().'/oauth/authorize', $state);
    }

    protected function getPublicBaseUrl(): string
    {
        return rtrim((string) config('services.omniauth.host', 'https://auth.admin.omnifexit.test'), '/');
    }

    protected function getTokenUrl(): string
    {
        return $this->getInternalBaseUrl().'/oauth/token';
    }

    protected function getInternalBaseUrl(): string
    {
        return rtrim((string) config('services.omniauth.internal_host', 'http://omnifexit-auth:80'), '/');
    }

    /**
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->getInternalBaseUrl().'/api/user', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
        ]);
    }
}
