<?php

namespace Omnifexit\OmniCore\Queue;

use Illuminate\Support\Facades\Redis;

/**
 * The Redis hash a receiver writes a job's progress to, so the sender can poll it (status, error, plus extra fields
 * such as an AI response). The key comes from the job's statusKey().
 */
class DispatchStatus
{
    /**
     * @param  array<string, string>  $extra
     */
    public static function mark(string $key, string $status, ?string $error = null, array $extra = [], int $ttl = 86400): void
    {
        $fields = ['updated_at' => now()->toIso8601String(), 'status' => $status, ...$extra];

        if ($error !== null) {
            $fields['error'] = $error;
        }

        Redis::hmset($key, $fields);
        Redis::expire($key, $ttl);
    }
}
