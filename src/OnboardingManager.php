<?php

namespace WF\Onboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OnboardingManager
{
    protected $steps;

    public function __construct(Model $user, OnboardingSteps $onboardingSteps)
    {
        $this->steps = $onboardingSteps->steps($user);
    }

    /**
     * @return \Illuminate\Support\Collection|\WF\Onboard\OnboardingStep[]
     */
    public function steps() : Collection
    {
        return $this->steps;
    }

    public function inProgress() : bool
    {
        return ! $this->finished();
    }

    public function finished() : bool
    {
        return $this->steps->filter(function (OnboardingStep $step) {
            return $step->incomplete();
        })->isEmpty();
    }

    public function finishedRequired() : bool
    {
        return $this->steps->filter(function (OnboardingStep $step) {
            return $step->required() && $step->incomplete();
        })->isEmpty();
    }

    public function nextUnfinishedStep() : ?OnboardingStep
    {
        return $this->steps->first(function (OnboardingStep $step) {
            return $step->incomplete();
        });
    }
}
