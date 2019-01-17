<?php

namespace WF\Onboard;

use Illuminate\Support\Facades\Facade;

/**
 * @see \WF\Onboard\OnboardingSteps
 */
class OnboardFacade extends Facade
{
    protected static function getFacadeAccessor() : string
    {
        return OnboardingSteps::class;
    }
}
