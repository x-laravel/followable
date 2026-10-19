<?php

namespace XLaravel\Followable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use LogicException;
use XLaravel\Followable\Contracts\CanFollow;
use XLaravel\Followable\Contracts\HasFollowers;
use XLaravel\Followable\Contracts\RequiresFollowApproval;
use XLaravel\Followable\Models\Follow;
use XLaravel\Followable\Support\FollowableConfig;

/**
 * @mixin Model
 */
trait Follower
{
    public static function bootFollower(): void
    {
        static::deleted(function (Model $model) {
            /** @var Model&self $model */
            $model->forgetSentFollowsWhenGone();
        });
    }

    /**
     * @return MorphMany<Follow, $this>
     */
    public function sentFollows(): MorphMany
    {
        return $this->morphMany(FollowableConfig::followModel(), 'follower');
    }

    /**
     * @template TFollowable of Model
     *
     * @param  class-string<TFollowable>  $followable
     * @return MorphToMany<TFollowable, $this>
     */
    public function followings(string $followable): MorphToMany
    {
        return $this->morphedByMany($followable, 'followable', FollowableConfig::followTable(), 'follower_id', 'followable_id')
            ->wherePivot('follower_type', $this->getMorphClass())
            ->wherePivotNotNull('accepted_at')
            ->withPivot('accepted_at')
            ->withTimestamps();
    }

    public function follow(Model&HasFollowers $followable): Follow
    {
        $this->ensureCanFollow();

        if ($followable->is($this)) {
            throw new InvalidArgumentException('A model cannot follow itself.');
        }

        return $this->sentFollows()->createOrFirst([
            'followable_id' => $followable->getKey(),
            'followable_type' => $followable->getMorphClass(),
        ], [
            'accepted_at' => $followable instanceof RequiresFollowApproval && $followable->requiresFollowApproval() ? null : Carbon::now(),
        ]);
    }

    public function unfollow(Model&HasFollowers $followable): bool
    {
        $follow = $this->sentFollows()->whereMorphedTo('followable', $followable)->first();

        return (bool) $follow?->delete();
    }

    public function isFollowing(Model&HasFollowers $followable): bool
    {
        return $this->sentFollows()->whereMorphedTo('followable', $followable)->whereNotNull('accepted_at')->exists();
    }

    public function hasRequestedToFollow(Model&HasFollowers $followable): bool
    {
        return $this->sentFollows()->whereMorphedTo('followable', $followable)->whereNull('accepted_at')->exists();
    }

    public function isMutuallyFollowing(Model&HasFollowers $other): bool
    {
        return $this->isFollowing($other)
            && FollowableConfig::followModel()::query()
                ->whereMorphedTo('follower', $other)
                ->whereMorphedTo('followable', $this)
                ->whereNotNull('accepted_at')
                ->exists();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWithFollowsBack(Builder $query, (Model&HasFollowers)|null $followable): void
    {
        $query->withExists(['sentFollows as follows_back' => function (Builder $follows) use ($followable) {
            if ($followable === null) {
                $follows->whereRaw('1 = 0');

                return;
            }

            $follows->whereMorphedTo('followable', $followable)->whereNotNull('accepted_at');
        }]);
    }

    protected function ensureCanFollow(): void
    {
        if (! $this instanceof CanFollow) {
            throw new LogicException(sprintf('Implement %s on [%s].', CanFollow::class, static::class));
        }
    }

    protected function forgetSentFollowsWhenGone(): void
    {
        if ($this->exists) {
            return;
        }

        $this->sentFollows()->delete();
    }
}
