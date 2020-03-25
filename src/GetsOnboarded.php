<?php

namespace WF\Onboard;

use Illuminate\Database\Eloquent\Builder;

/**
 * @method static \Illuminate\Database\Eloquent\Builder|static onboarded(bool $boarded = true)
 */
trait GetsOnboarded
{
    public function onboarding() : OnboardingManager
    {
        return app(OnboardingManager::class, ['user' => $this]);
    }

    public function scopeOnboarded(Builder $builder, bool $onboarded = true) : Builder
    {
        $clone = $onboarded ? null : clone $builder;
        $this->onboarding()->steps()->each(static function (OnboardingStep $step) use ($builder) : void {
            $step->applyScopes($builder);
        });

        if (! $onboarded) {
            return $clone->whereRaw(
                $builder->getQuery()->getGrammar()->reverseWheres($builder),
                $builder->getQuery()->getBindings()
            );
        }

        return $builder;
    }
}
