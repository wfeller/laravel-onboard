<?php

namespace WF\Onboard;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;

/**
 * Trait GetsOnboarded
 * @package WF\Onboard
 * @method static \Illuminate\Database\Eloquent\Builder|static onboarded(bool $boarded = true)
 */
trait GetsOnboarded
{
    public function onboarding() : OnboardingManager
    {
        return App::make(OnboardingManager::class, ['user' => $this]);
    }

    public function scopeOnboarded(Builder $builder, bool $boarded = true) : Builder
    {
        $clone = clone $builder;
        $this->onboarding()->steps()->each->applyScopes($builder);
        if (! $boarded) {
            $clone->whereRaw($builder->getQuery()->getGrammar()->reverseWheres($builder), $builder->getBindings());
            return $clone;
        }
        return $builder;
    }
}
