<?php

namespace WF\Onboard;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OnboardingSteps
{
    protected $stepsCache = [];
    protected $steps = [];

    public function addStep(string $code, string $onboardedClass) : OnboardingStep
    {
        return $this->steps[$onboardedClass][$code] = new OnboardingStep($code);
    }

    public function getRawSteps(string $onboardedClass) : array
    {
        return $this->steps[$onboardedClass];
    }

    /**
     * @param  object $user The current user
     * @return \Illuminate\Support\Collection|\WF\Onboard\OnboardingStep[]
     */
    public function steps(object $user) : Collection
    {
        $id = $this->getCacheId($user);

        if (! isset($this->stepsCache[$id])) {
            $this->setStepsFor($user, $id);
        }

        return collect($this->stepsCache[$id]);
    }

    protected function getCacheId(object $user) : string
    {
        if ($user instanceof Model && $user->exists) {
            return $user->getConnectionName().$user->getTable().$user->getKey();

        }

        return (string) spl_object_id($user);
    }

    protected function setStepsFor(object $user, $id) : void
    {
        $this->stepsCache[$id] = [];

        if (isset($this->steps[get_class($user)])) {
            foreach ($this->steps[get_class($user)] as $code => $step) {
                $this->stepsCache[$id][$code] = (clone $step)->setUser($user);
            }
        }
    }
}
