<?php

namespace Calebporzio\Onboard;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Class OnboardingStep
 * @package Calebporzio\Onboard
 * @property-read string $title
 * @property-read string|null $cta
 * @property-read string|null $link
 */
class OnboardingStep
{
    protected $attributes = [];
    protected $completeIf;
    protected $completeScope;
    protected $requiredIf;
    protected $requiredScope;
    protected $user;

    public function __construct(string $title)
    {
        $this->attributes(['title' => $title]);
    }

    public function cta(string $cta) : self
    {
        $this->attributes(['cta' => $cta]);

        return $this;
    }

    public function link(string $link) : self
    {
        $this->attributes(['link' => $link]);

        return $this;
    }

    public function completeIf(callable $callback) : self
    {
        $this->completeIf = $callback;

        return $this;
    }

    public function completeScope(callable $callback) : self
    {
        $this->completeScope = $callback;

        return $this;
    }

    public function requiredIf(callable $callback) : self
    {
        $this->requiredIf = $callback;

        return $this;
    }

    public function requiredScope(callable $callback) : self
    {
        $this->requiredScope = $callback;

        return $this;
    }

    public function setUser($user) : self
    {
        $this->user = $user;

        return $this;
    }

    public function complete() : bool
    {
        if ($this->completeIf && $this->user) {
            return !! call_user_func_array($this->completeIf, [$this->user]);
        }

        return false;
    }

    public function incomplete() : bool
    {
        return ! $this->complete();
    }

    public function required() : bool
    {
        if ($this->requiredIf && $this->user) {
            return !! call_user_func_array($this->requiredIf, [$this->user]);
        }

        return true;
    }

    public function optional() : bool
    {
        return ! $this->required();
    }

    public function applyScopes(Builder $builder) : void
    {
        if (! $this->completeScope) {
            throw new \LogicException("Missing scope for step '{$this->title}' and class '".get_class($this->user)."'");
        }
        if ($this->requiredScope) {
            static::registerBuilderMacros();
            $builder->where(function (Builder $builder) {
                $builder
                    ->orWhere(function (Builder $builder) {
                        $dummyBuilder = new Builder(clone $builder->getQuery());
                        call_user_func_array($this->requiredScope, [$dummyBuilder]);
                        $builder->whereRaw(
                            $dummyBuilder->getQuery()->getGrammar()->reverseWheres($dummyBuilder),
                            $dummyBuilder->getBindings()
                        );
                    })
                    ->orWhere(function (Builder $builder) {
                        $this->applyScope($this->requiredScope, $builder);
                        $this->applyScope($this->completeScope, $builder);
                    });
            });
        } else {
            $this->applyScope($this->completeScope, $builder);
        }
    }

    private function applyScope(callable $scope, Builder $builder) : void
    {
        $builder->where(function (Builder $query) use ($scope) {
            call_user_func_array($scope, [$query]);
        });
    }

    public function attribute($key, $default = null)
    {
        return Arr::get($this->attributes, $key, $default);
    }

    public function attributes(array $attributes) : self
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    public function __get($key)
    {
        return $this->attribute($key);
    }

    protected static function registerBuilderMacros() : void
    {
        Grammar::macro('reverseWheres', function (Builder $dummy) {
            $wheres = $this->compileWheresToArray($dummy->getQuery());
            array_walk($wheres, function (&$value) {
                if (Str::startsWith($value, ['and', 'And', 'AND'])) {
                    $value = 'or NOT' . substr($value, 3);
                } elseif (Str::startsWith($value, ['or', 'Or', 'OR'])) {
                    $value = 'and NOT' . substr($value, 2);
                }
            });
            return Str::replaceFirst('where ', '', $this->concatenateWhereClauses(null, $wheres));
        });
    }
}
