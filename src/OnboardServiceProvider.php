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
            foreach ($wheres as &$where) {
                if (Str::startsWith($where, ['and', 'And', 'AND'])) {
                    $where = 'or NOT' . substr($where, 3);
                } elseif (Str::startsWith($where, ['or', 'Or', 'OR'])) {
                    $where = 'and NOT' . substr($where, 2);
                }
            }
            /** @var Grammar $this */
            return Str::replaceFirst('where ', '', $this->concatenateWhereClauses(null, $wheres));
        });
    }

    public function register() : void
    {
        $this->app->singleton(OnboardingSteps::class);
    }
}
