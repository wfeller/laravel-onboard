<?php

namespace WF\Onboard;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class OnboardServiceProvider extends ServiceProvider
{
    public function boot() : void
    {
        Grammar::macro('reverseWheres', function (Builder $builder) : string {
            /** @var Grammar $this */
            $wheres = $this->compileWheresToArray($builder->getQuery());
            array_walk($wheres, function (&$value) {
                if (Str::startsWith($value, ['and', 'And', 'AND'])) {
                    $value = 'or NOT' . substr($value, 3);
                } elseif (Str::startsWith($value, ['or', 'Or', 'OR'])) {
                    $value = 'and NOT' . substr($value, 2);
                }
            });
            /** @var Grammar $this */
            return Str::replaceFirst('where ', '', $this->concatenateWhereClauses(null, $wheres));
        });
    }

    public function register() : void
    {
        $this->app->singleton(OnboardingSteps::class);
    }
}
