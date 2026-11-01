<?php

namespace Omnifexit\OmniCore\Auth;

/**
 * OmniAuth's lookup of the pairwise queue key it shares with each client app ("omniauth-{client slug}"). OmniAuth
 * binds an implementation; client apps don't need one.
 */
interface ClientQueueKeys
{
    /**
     * The key name shared with the OAuth client with this id.
     */
    public function forClient(?string $clientId): string;

    /**
     * Every client app's key name.
     *
     * @return list<string>
     */
    public function all(): array;
}
