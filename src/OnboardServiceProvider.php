<?php

namespace Calebporzio\Onboard;

use Illuminate\Support\ServiceProvider;

class OnboardServiceProvider extends ServiceProvider
{
    public function register() : void
    {
        $this->app->singleton(OnboardingSteps::class);
    }
}
