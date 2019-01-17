<?php

namespace WF\Onboard;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \WF\Onboard\OnboardingStep addStep(string $title, string|array $userClass = null)
 * @method static \Illuminate\Support\Collection|\WF\Onboard\OnboardingStep[] steps(object $user)
 *
 * @see \WF\Onboard\OnboardingSteps
 */
class OnboardFacade extends Facade
{
    protected static function getFacadeAccessor() : string
    {
        return OnboardingSteps::class;
    }
}
