<?php

namespace XLaravel\Followable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use XLaravel\Followable\Concerns\Followable;
use XLaravel\Followable\Contracts\HasFollowers;

/**
 * @property int $id
 * @property string $name
 */
class Venue extends Model implements HasFollowers
{
    use Followable;

    public $timestamps = false;

    protected $guarded = [];
}
