<?php

namespace App\Services\Scan;

use App\Models\Attendee;

final readonly class ScanResult
{
    public function __construct(
        public ScanOutcome $outcome,
        public ScanReason $reason,
        public ?Attendee $attendee = null,
    ) {}
}
