<?php

namespace WF\Onboard;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;

/**
 * Trait GetsOnboarded
 * @package WF\Onboard
 * @method static \Illuminate\Database\Eloquent\Builder|static onboarded()
 */
trait GetsOnboarded
{
    public function onboarding() : OnboardingManager
    {
        return App::make(OnboardingManager::class, ['user' => $this]);
    }

    public function scopeOnboarded(Builder $builder) : Builder
    {
        $this->onboarding()->steps()->each->applyScopes($builder);
        return $builder;
    }
}
