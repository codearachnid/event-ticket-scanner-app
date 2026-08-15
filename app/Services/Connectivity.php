<?php

namespace App\Services;

use Native\Mobile\Facades\Network;
use Throwable;

class Connectivity
{
    /**
     * Only an explicit `connected: false` from the native bridge counts as
     * offline. Anything indeterminate — null (no bridge), a bridge error
     * object like {status:"error", code:"NO_DEVICE"} (dev machine with the
     * Jump bridge but no device attached) — is treated as online, so sync
     * proceeds and the HTTP layer surfaces any real failure.
     */
    public function isOnline(): bool
    {
        try {
            $status = Network::status();
        } catch (Throwable) {
            return true;
        }

        if ($status === null || ! property_exists($status, 'connected')) {
            return true;
        }

        return (bool) $status->connected;
    }
}
