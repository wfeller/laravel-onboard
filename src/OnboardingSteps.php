<?php

namespace WF\Onboard;

use Illuminate\Support\Collection;

class OnboardingSteps
{
    protected $steps = [];

    /**
     * @param string $title The title of the step.
     * @param array|string|null $userClass The class or classes this step should be applied to (aka App\User, App\Team, etc.)
     * @return \WF\Onboard\OnboardingStep
     */
    public function addStep(string $title, $userClass = null) : OnboardingStep
    {
        if (is_array($userClass) && empty($userClass)) {
            throw new \UnexpectedValueException('Cannot add step to an empty array.');
        }

        $userClass = $userClass ?? [null];

        foreach ((array) $userClass as $class) {
            $class = $class ?? 'null';
            if (! isset($this->steps[$class])) {
                $this->steps[$class] = [];
            }

            $this->steps[$class][] = $step = new OnboardingStep($title);
        }

        return $step;
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
