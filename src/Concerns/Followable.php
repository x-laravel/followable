<?php

namespace XLaravel\Followable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use XLaravel\Followable\Contracts\CanFollow;
use XLaravel\Followable\Models\Follow;
use XLaravel\Followable\Support\FollowableConfig;

/**
 * @mixin Model
 */
trait Followable
{
    public static function bootFollowable(): void
    {
        static::deleted(function (Model $model) {
            /** @var Model&self $model */
            $model->forgetReceivedFollowsWhenGone();
        });
    }

    /**
     * @return MorphMany<Follow, $this>
     */
    public function receivedFollows(): MorphMany
    {
        return $this->morphMany(FollowableConfig::followModel(), 'followable');
    }

    /**
     * @param  class-string<Model>|null  $follower
     * @return MorphToMany<Model, $this>
     */
    public function followers(?string $follower = null): MorphToMany
    {
        return $this->followerRelation($follower)->wherePivotNotNull('accepted_at');
    }

    /**
     * @param  class-string<Model>|null  $follower
     * @return MorphToMany<Model, $this>
     */
    public function followRequests(?string $follower = null): MorphToMany
    {
        return $this->followerRelation($follower)->wherePivotNull('accepted_at');
    }

    /**
     * @param  class-string<Model>|null  $follower
     * @return MorphToMany<Model, $this>
     */
    public function mutualFollowers(?string $follower = null): MorphToMany
    {
        $relation = $this->followers($follower);
        $related = $relation->getRelated();

        return $relation->whereExists(fn (QueryBuilder $query) => $query
            ->from(FollowableConfig::followTable() . ' as followed_back')
            ->where('followed_back.follower_type', $this->getMorphClass())
            ->where('followed_back.follower_id', $this->getKey())
            ->where('followed_back.followable_type', $related->getMorphClass())
            ->whereColumn('followed_back.followable_id', $related->getQualifiedKeyName())
            ->whereNotNull('followed_back.accepted_at'));
    }

    public function isFollowedBy((Model&CanFollow)|null $follower): bool
    {
        if ($follower === null) {
            return false;
        }

        return $this->receivedFollows()->whereMorphedTo('follower', $follower)->whereNotNull('accepted_at')->exists();
    }

    public function hasFollowRequestFrom((Model&CanFollow)|null $follower): bool
    {
        if ($follower === null) {
            return false;
        }

        return $this->receivedFollows()->whereMorphedTo('follower', $follower)->whereNull('accepted_at')->exists();
    }

    public function acceptFollowRequest(Model&CanFollow $follower): bool
    {
        $request = $this->receivedFollows()->whereMorphedTo('follower', $follower)->whereNull('accepted_at')->first();

        return $request !== null && $request->accept();
    }

    public function rejectFollowRequest(Model&CanFollow $follower): bool
    {
        $request = $this->receivedFollows()->whereMorphedTo('follower', $follower)->whereNull('accepted_at')->first();

        return $request !== null && $request->reject();
    }

    public function removeFollower(Model&CanFollow $follower): bool
    {
        $follow = $this->receivedFollows()->whereMorphedTo('follower', $follower)->whereNotNull('accepted_at')->first();

        return (bool) $follow?->delete();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWithFollowedBy(Builder $query, (Model&CanFollow)|null $follower): void
    {
        $query->withExists([
            'receivedFollows as is_followed' => $this->followState($follower, accepted: true),
            'receivedFollows as is_follow_requested' => $this->followState($follower, accepted: false),
        ]);
    }

    /**
     * @param  class-string<Model>|null  $follower
     * @return MorphToMany<Model, $this>
     */
    protected function followerRelation(?string $follower): MorphToMany
    {
        $follower ??= FollowableConfig::followerModel();

        return $this->morphToMany($follower, 'followable', FollowableConfig::followTable(), 'followable_id', 'follower_id')
            ->wherePivot('follower_type', (new $follower())->getMorphClass())
            ->withPivot('accepted_at')
            ->withTimestamps();
    }

    protected function followState((Model&CanFollow)|null $follower, bool $accepted): Closure
    {
        return function (Builder $follows) use ($follower, $accepted) {
            if ($follower === null) {
                $follows->whereRaw('1 = 0');

                return;
            }

            $follows->whereMorphedTo('follower', $follower)->whereNull('accepted_at', not: $accepted);
        };
    }

    protected function forgetReceivedFollowsWhenGone(): void
    {
        if ($this->exists) {
            return;
        }

        $this->receivedFollows()->delete();
    }
}
