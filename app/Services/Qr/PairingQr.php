<?php

namespace App\Services\Qr;

/**
 * Parsed contents of the app-pairing QR from wp-admin (Tickets → Settings →
 * Integrations). Only the site URL is used — we authenticate with Application
 * Passwords, never with the QR's shared api_key.
 */
final readonly class PairingQr
{
    public function __construct(public string $url) {}
}
