<?php

namespace XLaravel\Followable\Contracts;

interface RequiresFollowApproval
{
    public function requiresFollowApproval(): bool;
}
