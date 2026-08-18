<?php

namespace App\NativeComponents;

use App\Models\Site;
use App\Services\Api\ApiClient;
use App\Services\Api\ApiException;
use App\Services\SiteCredentials;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class ConnectSite extends NativeComponent
{
    public string $siteUrl = '';

    public string $username = '';

    public string $password = '';

    public string $error = '';

    public bool $busy = false;

    public function connect(): void
    {
        $this->error = '';

        $url = $this->normalizeUrl($this->siteUrl);

        if ($url === null) {
            $this->error = 'Enter the site address as a full https:// URL.';

            return;
        }

        if (trim($this->username) === '' || trim($this->password) === '') {
            $this->error = 'Username and application password are required.';

            return;
        }

        if (Site::where('base_url', $url)->where('username', trim($this->username))->exists()) {
            $this->error = 'That site is already connected for this user.';

            return;
        }

        $this->busy = true;

        // The password must be in SecureStorage BEFORE the verify call — the
        // HTTP client reads it from there. Everything rolls back on failure.
        $site = Site::create([
            'name' => parse_url($url, PHP_URL_HOST),
            'base_url' => $url,
            'username' => trim($this->username),
        ]);

        app(SiteCredentials::class)->store($site, trim($this->password));

        try {
            $me = app(ApiClient::class)->me($site);
        } catch (ApiException $e) {
            $this->abortConnect($site, $e->isAuthFailure()
                ? 'The site rejected these credentials. Check the username and application password.'
                : ($e->status === 404
                    ? 'The TEC Scanner companion plugin does not appear to be installed on this site.'
                    : "Could not reach the site: {$e->getMessage()}"));

            return;
        }

        if (! ($me['capabilities']['can_checkin'] ?? false)) {
            $this->abortConnect($site, 'This user is not allowed to manage check-ins. Ask an administrator for the check-in capability.');

            return;
        }

        $site->update(['name' => $me['site_name'], 'last_verified_at' => now()]);

        $this->password = ''; // never keep it in component state longer than needed
        $this->busy = false;

        $this->replace('/events');
    }

    private function abortConnect(Site $site, string $message): void
    {
        app(SiteCredentials::class)->forget($site);
        $site->delete();

        $this->busy = false;
        $this->error = $message;
    }

    /** Require https:// (plain http only in debug builds for local test sites). */
    private function normalizeUrl(string $raw): ?string
    {
        $raw = rtrim(trim($raw), '/');

        if ($raw === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $raw)) {
            $raw = 'https://'.$raw;
        }

        if (! filter_var($raw, FILTER_VALIDATE_URL)) {
            return null;
        }

        if (str_starts_with(strtolower($raw), 'http://') && ! config('app.debug')) {
            return null;
        }

        return $raw;
    }

    public function navTitle(): string
    {
        return 'Connect a Site';
    }

    public function render(): View
    {
        return view('native.connect-site');
    }
}
