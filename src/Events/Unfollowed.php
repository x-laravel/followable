<?php

namespace XLaravel\Followable\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use XLaravel\Followable\Models\Follow;

final readonly class Unfollowed implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Follow $follow,
    ) {
    }
}
