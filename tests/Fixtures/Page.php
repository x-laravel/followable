<?php

namespace XLaravel\Followable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use XLaravel\Followable\Concerns\Follower;
use XLaravel\Followable\Contracts\CanFollow;

/**
 * @property int $id
 * @property string $name
 */
class Page extends Model implements CanFollow
{
    use Follower;

    public $timestamps = false;

    protected $guarded = [];
}
