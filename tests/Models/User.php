<?php

namespace WF\Onboard\Test\Models;

use Illuminate\Database\Eloquent\Builder;
use WF\Onboard\GetsOnboarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Class User
 * @package WF\Onboard\Test\Models
 * @property string $name
 * @property string $age
 * @method static \Illuminate\Database\Eloquent\Builder|User named(string $name)
 * @method static \Illuminate\Database\Eloquent\Builder|User hasAnyName(string... $names)
 */
class User extends Model
{
    use GetsOnboarded;

    protected $guarded = [];

    public function scopeNamed(Builder $builder, string $name) : Builder
    {
        return $builder->where('name', $name);
    }

    public function scopeHasAnyName(Builder $builder, string... $names) : Builder
    {
        return $builder->orWhere(function (Builder $builder) use ($names) {
            foreach ($names as $name) {
                $builder->orWhere('name', $name);
            }
        });
    }
}
