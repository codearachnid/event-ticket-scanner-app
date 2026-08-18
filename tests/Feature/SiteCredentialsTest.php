<?php

use App\Models\Site;
use App\Services\SiteCredentials;
use Illuminate\Support\Facades\DB;
use Native\Mobile\Testing\Native;

covers(SiteCredentials::class);

beforeEach(function () {
    $this->site = Site::factory()->create();
    $this->credentials = new SiteCredentials;
});

it('uses SecureStorage when the native side works, without a fallback copy', function () {
    $bridge = Native::fakeBridge();
    $bridge->respondTo('SecureStorage.Set', ['success' => true]);
    $bridge->respondTo('SecureStorage.Get', ['status' => 'found', 'value' => 'abcd efgh']);

    $this->credentials->store($this->site, 'abcd efgh');

    expect(DB::table('device_secrets')->count())->toBe(0)
        ->and($this->credentials->passwordFor($this->site))->toBe('abcd efgh');
});

it('falls back to the encrypted device_secrets store when SecureStorage is unavailable', function () {
    // Fake bridge with NO SecureStorage responders — set() returns false,
    // exactly like a build compiled without the premium plugin.
    Native::fakeBridge();

    $this->credentials->store($this->site, 'abcd efgh ijkl mnop');

    $row = DB::table('device_secrets')->where('key', $this->site->credentialKey())->first();
    expect($row)->not->toBeNull()
        ->and($row->value)->not->toContain('abcd')          // encrypted at rest
        ->and($this->credentials->passwordFor($this->site))->toBe('abcd efgh ijkl mnop');
});

it('forget() clears the fallback store', function () {
    Native::fakeBridge();

    $this->credentials->store($this->site, 'secret');
    $this->credentials->forget($this->site);

    expect(DB::table('device_secrets')->count())->toBe(0)
        ->and($this->credentials->passwordFor($this->site))->toBeNull();
});

it('a successful native store cleans up an older fallback copy', function () {
    Native::fakeBridge();
    $this->credentials->store($this->site, 'old-fallback');   // lands in fallback

    $bridge = Native::fakeBridge();
    $bridge->respondTo('SecureStorage.Set', ['success' => true]);
    $this->credentials->store($this->site, 'now-native');

    expect(DB::table('device_secrets')->count())->toBe(0);
});
