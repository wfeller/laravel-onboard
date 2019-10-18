<?php

namespace WF\Onboard;

use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\ServiceProvider;
use WF\Onboard\Grammar\GrammarMacro;

class OnboardServiceProvider extends ServiceProvider
{
    public function boot() : void
    {
        Grammar::mixin(new GrammarMacro);
    }

    public function register() : void
    {
        $this->app->singleton(OnboardingSteps::class);
    }
}
