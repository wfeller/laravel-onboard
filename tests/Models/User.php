<?php

namespace Calebporzio\Onboard\Test\Models;

use Calebporzio\Onboard\GetsOnboarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Class User
 * @package Calebporzio\Onboard\Test\Models
 * @property string $name
 * @property string $age
 */
class User extends Model
{
    use GetsOnboarded;

    protected $guarded = [];
}
