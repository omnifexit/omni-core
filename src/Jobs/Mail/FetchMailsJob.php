<?php

namespace Omnifexit\OmniCore\Jobs\Mail;

use Illuminate\Support\Str;
use Omnifexit\OmniCore\Jobs\ServiceJob;

/**
 * Fetches new mail from the inbox. Sent by OmniDesk (the "fetch now" button) and by OmniMail's own schedule; the
 * result goes back to OmniDesk as a SaveEmailsJob.
 */
class FetchMailsJob extends ServiceJob
{
    public string $dispatchId;

    public function __construct(?string $dispatchId = null)
    {
        $this->dispatchId = $dispatchId ?? (string) Str::uuid();
    }

    public static function queue(?string $receiver = null): string
    {
        return 'mail-fetch-queue';
    }

    public static function senderKeys(): array
    {
        return [
            'omnidesk' => 'omnidesk-omnimail',
            'omnimail' => 'omnidesk-omnimail',
        ];
    }

    /**
     * The Redis hash the receiver writes this job's progress to (DispatchStatus::mark()), for the sender to poll.
     */
    public function statusKey(): string
    {
        return "mail:fetch:{$this->dispatchId}";
    }
}
