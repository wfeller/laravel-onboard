<?php

namespace WF\Onboard;

use Illuminate\Support\Collection;

class OnboardingSteps
{
    protected $steps = [];

    public function addStep(string $code, string $onboardedClass) : OnboardingStep
    {
        return $this->steps[$onboardedClass][$code] = new OnboardingStep($code);
    }

    /**
     * @param  object $user The current user
     * @return \Illuminate\Support\Collection|\WF\Onboard\OnboardingStep[]
     */
    public function steps(object $user) : Collection
    {
        // Load each step with the current User object.
        if (isset($this->steps[get_class($user)])) {
            $steps = $this->steps[get_class($user)];
        } elseif (isset($this->steps['null'])) {
            $steps = $this->steps['null'];
        } else {
            $steps = [];
        }
        return collect($steps)->map->setUser($user);
    }
}
