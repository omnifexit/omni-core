<?php

namespace Omnifexit\OmniCore\Sso\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class UserData extends Data
{
    /**
     * @param  DataCollection<int, RoleData>  $roles
     */
    public function __construct(
        public mixed $email_verified_at,
        public string $email,
        public string $name,
        public string $id,

        #[DataCollectionOf(RoleData::class)]
        public DataCollection $roles,

        public ?string $discord_id = null,
    ) {}
}
