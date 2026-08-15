<?php

namespace App\Services;

use Native\Mobile\Facades\Device;
use Throwable;

/**
 * Stable identifier sent as `device_id` with check-in batches, so other
 * devices (and wp-admin) can show who performed a check-in. A friendly
 * name (Settings screen, Stage 6) will take precedence once implemented.
 */
class DeviceIdentity
{
    public function id(): string
    {
        if ($name = config('ticketscanner.device_name')) {
            return $name;
        }

        try {
            $id = Device::getId();

            if (is_string($id) && $id !== '') {
                return $id;
            }
        } catch (Throwable) {
            // Native bridge absent (dev machine / tests) — fall through.
        }

        return 'dev-device';
    }
}
