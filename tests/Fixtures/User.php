<?php

namespace XLaravel\Followable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use XLaravel\Followable\Concerns\Followable;
use XLaravel\Followable\Concerns\Follower;
use XLaravel\Followable\Contracts\CanFollow;
use XLaravel\Followable\Contracts\HasFollowers;
use XLaravel\Followable\Contracts\RequiresFollowApproval;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_private
 */
class User extends Model implements CanFollow, HasFollowers, RequiresFollowApproval
{
    use Followable;
    use Follower;
    use SoftDeletes;

    public $timestamps = false;

    protected $guarded = [];

    protected $attributes = [
        'is_private' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
        ];
    }

    public function requiresFollowApproval(): bool
    {
        return $this->is_private;
    }
}
