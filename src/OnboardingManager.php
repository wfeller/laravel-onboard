<?php

namespace WF\Onboard;

use Illuminate\Support\Collection;

class OnboardingManager
{
    protected Collection $steps;

    public function __construct(object $user, OnboardingSteps $onboardingSteps)
    {
        $this->steps = $onboardingSteps->steps($user);
    }

    public function resetCache() : self
    {
        foreach ($this->steps as $step) {
            $step->clearCache();
        }

        return $this;
    }

    public function step(string $code) : ?OnboardingStep
    {
        return $this->steps->get($code);
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
        return $this->steps->filter(static fn (OnboardingStep $step) : bool => $step->incomplete())->isEmpty();
    }

    public function finishedRequired() : bool
    {
        return $this->steps
            ->filter(static fn (OnboardingStep $step) : bool => $step->required() && $step->incomplete())
            ->isEmpty();
    }

    public function nextUnfinishedStep() : ?OnboardingStep
    {
        return $this->steps->first(static fn (OnboardingStep $step) : bool => $step->incomplete());
    }
}
