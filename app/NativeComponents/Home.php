<?php

namespace App\NativeComponents;

use App\Models\Site;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Root gate: no visible UI of its own — routes to onboarding when no site is
 * connected yet, otherwise to the events list.
 */
class Home extends NativeComponent
{
    public function mount(): void
    {
        $this->replace(Site::query()->exists() ? '/events' : '/connect');
    }

    public function render(): View
    {
        return view('native.home');
    }
}
