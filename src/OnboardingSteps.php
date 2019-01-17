<?php

namespace WF\Onboard;

use Illuminate\Support\Collection;

class OnboardingSteps
{
    protected $stepsCache = [];

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
        $id = spl_object_id($user);

        if (! isset($this->stepsCache[$id])) {
            $this->stepsCache[$id] = [];
            if (isset($this->steps[get_class($user)])) {
                foreach ($this->steps[get_class($user)] as $code => $step) {
                    $this->stepsCache[$id][$code] = clone $step;
                }
            }
        }

        return collect($this->stepsCache[$id])->map->setUser($user);
    }
}
