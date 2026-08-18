<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Native\Mobile\Facades\SecureStorage;
use Throwable;

/**
 * The only gateway to site application passwords.
 *
 * Primary store: SecureStorage (iOS Keychain / Android Keystore) — used
 * whenever the premium nativephp/mobile-secure-storage native side is
 * compiled into the build.
 *
 * Fallback store: the `device_secrets` table, Crypt-encrypted with APP_KEY.
 * The NativePHP shell generates APP_KEY on-device and keeps it in the OS
 * keychain, so fallback ciphertext is still keychain-protected at one
 * remove. The fallback exists because builds without the premium plugin
 * have no native SecureStorage at all (its set() returns false).
 *
 * Passwords never appear in SQLite as plaintext, in logs, or in config.
 */
class SiteCredentials
{
    public function store(Site $site, string $applicationPassword): bool
    {
        if ($this->secureSet($site->credentialKey(), $applicationPassword)) {
            // Native store succeeded — remove any stale fallback copy.
            DB::table('device_secrets')->where('key', $site->credentialKey())->delete();

            return true;
        }

        DB::table('device_secrets')->updateOrInsert(
            ['key' => $site->credentialKey()],
            ['value' => Crypt::encryptString($applicationPassword), 'updated_at' => now(), 'created_at' => now()],
        );

        return true;
    }

    public function passwordFor(Site $site): ?string
    {
        $value = $this->secureGet($site->credentialKey());

        if ($value !== null && $value !== '') {
            return $value;
        }

        $row = DB::table('device_secrets')->where('key', $site->credentialKey())->first();

        if (! $row) {
            return null;
        }

        try {
            return Crypt::decryptString($row->value);
        } catch (Throwable) {
            return null; // APP_KEY rotated/corrupt payload — treat as absent.
        }
    }

    public function forget(Site $site): bool
    {
        try {
            SecureStorage::delete($site->credentialKey());
        } catch (Throwable) {
            // Native side absent — nothing stored there anyway.
        }

        DB::table('device_secrets')->where('key', $site->credentialKey())->delete();

        return true;
    }

    private function secureSet(string $key, string $value): bool
    {
        try {
            return SecureStorage::set($key, $value);
        } catch (Throwable) {
            return false;
        }
    }

    private function secureGet(string $key): ?string
    {
        try {
            return SecureStorage::get($key);
        } catch (Throwable) {
            return null;
        }
    }
}
