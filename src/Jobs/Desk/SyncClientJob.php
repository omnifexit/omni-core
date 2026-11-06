<?php

namespace Omnifexit\OmniCore\Jobs\Desk;

use Omnifexit\OmniCore\Jobs\ServiceJob;

/**
 * A customer that changed in OmniSales, for OmniDesk's read-only copy of its clients.
 */
class SyncClientJob extends ServiceJob
{
    /**
     * @param  array{company_name: ?string, name: ?string, contact_person: ?string, email: ?string, phone: ?string, status: ?string, tier: ?string, owner_email: ?string}  $client
     */
    public function __construct(
        public readonly int $salesCustomerId,
        public readonly bool $deleted,
        public readonly array $client = [],
    ) {}

    public static function queueName(?string $receiver = null): string
    {
        return 'client-sync-queue';
    }

    public static function senderKeys(): array
    {
        return ['omnisales' => 'omnidesk-omnisales'];
    }
}
