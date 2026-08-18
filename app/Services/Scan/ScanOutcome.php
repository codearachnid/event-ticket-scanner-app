<?php

namespace App\Services\Scan;

enum ScanOutcome: string
{
    case Green = 'green'; // valid — check them in
    case Amber = 'amber'; // valid ticket, but already checked in
    case Red = 'red';     // invalid — do not admit
}
