<?php

namespace WF\Onboard\Test\Models;

use WF\Onboard\GetsOnboarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Class User
 * @package WF\Onboard\Test\Models
 * @property string $name
 * @property string $age
 */
class User extends Model
{
    use GetsOnboarded;

    protected $guarded = [];
}
