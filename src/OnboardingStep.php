<?php

namespace WF\Onboard;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Macroable;
use LogicException;

/**
 * @property-read string $code
 * @property-read string|mixed|null $title
 * @property-read string|mixed|null $cta
 * @property-read string|mixed|null $link
 */
class OnboardingStep implements Arrayable
{
    use Macroable;

    protected $attributes = [];
    protected $completeIf;
    protected $completeScope;
    protected $requiredIf;
    protected $requiredScope;
    protected $neverRequired = false;
    protected $user;

    public function __construct(string $code)
    {
        $this->setAttributes(['code' => $code, 'title' => $code]);
    }

    public function title($title) : self
    {
        return $this->setAttributes(['title' => $title]);
    }

    public function cta($cta) : self
    {
        return $this->setAttributes(['cta' => $cta]);
    }

    public function link($link) : self
    {
        return $this->setAttributes(['link' => $link]);
    }

    public function completeIf(Closure $callback) : self
    {
        $this->completeIf = $callback;

        return $this;
    }

    public function completeScope(Closure $callback) : self
    {
        $this->completeScope = $callback;

        return $this;
    }

    public function requiredIf(Closure $callback) : self
    {
        $this->requiredIf = $callback;

        return $this;
    }

    public function requiredScope(Closure $callback) : self
    {
        $this->requiredScope = $callback;

        return $this;
    }

    public function neverRequired() : self
    {
        $this->neverRequired = true;

        return $this;
    }

    public function setUser(object $user) : self
    {
        $this->user = $user;

        foreach ($this->attributes as &$attribute) {
            if ($attribute instanceof Closure) {
                $attribute = $attribute($user);
            }
        }

        return $this;
    }

    public function complete() : bool
    {
        if ($this->completeIf && $this->user) {
            return !! ($this->completeIf)($this->user);
        }

        return false;
    }

    public function incomplete() : bool
    {
        return ! $this->complete();
    }

    public function required() : bool
    {
        if ($this->neverRequired) {
            return false;
        }

        if ($this->requiredIf && $this->user) {
            return !! ($this->requiredIf)($this->user);
        }

        return true;
    }

    public function optional() : bool
    {
        return ! $this->required();
    }

    public function applyScopes(Builder $builder) : void
    {
        if ($this->neverRequired) {
            return;
        }

        if (! $this->completeScope) {
            throw new LogicException("Missing scope for step '{$this->title}' and class '".get_class($this->user)."'");
        }

        if ($this->requiredScope) {
            $builder->where(function (Builder $builder) {
                $builder
                    ->orWhere(function (Builder $builder) {
                        $dummyBuilder = clone $builder;
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

    private function applyScope(Closure $scope, Builder $builder) : void
    {
        $builder->where(function (Builder $query) use ($scope) {
            $scope($query);
        });
    }

    public function hasAttribute(string $key) : bool
    {
        return Arr::has($this->attributes, $key);
    }

    public function getAttribute($key, $default = null)
    {
        return Arr::get($this->attributes, $key, $default);
    }

    public function setAttributes(array $attributes) : self
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    public function toArray() : array
    {
        return [
            'code' => $this->code,
            'required' => $this->required(),
            'complete' => $this->complete(),
            'attributes' => $this->attributes,
        ];
    }
}
