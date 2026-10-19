<?php

namespace XLaravel\Followable\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use XLaravel\Followable\Events\Followed;
use XLaravel\Followable\Events\FollowRequestAccepted;
use XLaravel\Followable\Events\FollowRequestCancelled;
use XLaravel\Followable\Events\FollowRequested;
use XLaravel\Followable\Events\FollowRequestRejected;
use XLaravel\Followable\Events\Unfollowed;
use XLaravel\Followable\Support\FollowableConfig;
use XLaravel\Followable\Support\MissingMorphTargets;

/**
 * @property int $id
 * @property string $follower_type
 * @property int|string $follower_id
 * @property string $followable_type
 * @property int|string $followable_id
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Follow extends Model
{
    use MassPrunable;

    protected $fillable = [
        'follower_id',
        'follower_type',
        'followable_id',
        'followable_type',
        'accepted_at',
    ];

    protected bool $rejecting = false;

    protected static function booted(): void
    {
        static::created(function (Follow $follow) {
            Event::dispatch($follow->isAccepted() ? new Followed($follow) : new FollowRequested($follow));
        });

        static::updated(function (Follow $follow) {
            if ($follow->wasChanged('accepted_at') && $follow->getOriginal('accepted_at') === null && $follow->isAccepted()) {
                Event::dispatch(new FollowRequestAccepted($follow));
            }
        });

        static::deleted(function (Follow $follow) {
            Event::dispatch(match (true) {
                $follow->isAccepted() => new Unfollowed($follow),
                $follow->rejecting => new FollowRequestRejected($follow),
                default => new FollowRequestCancelled($follow),
            });
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->where(fn (Builder $query) => MissingMorphTargets::where($query, 'followable'))
            ->orWhere(fn (Builder $query) => MissingMorphTargets::where($query, 'follower'));
    }

    public function getTable(): string
    {
        return FollowableConfig::followTable();
    }

    public function getConnectionName(): ?string
    {
        return FollowableConfig::connection() ?? parent::getConnectionName();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function follower(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function followable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeAccepted(Builder $query): void
    {
        $query->whereNotNull($this->qualifyColumn('accepted_at'));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('accepted_at'));
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted();
    }

    public function accept(): bool
    {
        if ($this->isAccepted()) {
            return false;
        }

        $this->accepted_at = Carbon::now();

        return $this->save();
    }

    public function reject(): bool
    {
        if ($this->isAccepted()) {
            return false;
        }

        $this->rejecting = true;

        try {
            return (bool) $this->delete();
        } finally {
            $this->rejecting = false;
        }
    }
}
