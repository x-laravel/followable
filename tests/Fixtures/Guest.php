<?php

namespace XLaravel\Followable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use XLaravel\Followable\Concerns\Follower;

/**
 * @property int $id
 * @property string $name
 */
class Guest extends Model
{
    use Follower;

    public $timestamps = false;

    protected $table = 'users';

    protected $guarded = [];
}
