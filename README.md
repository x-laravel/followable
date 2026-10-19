# Followable for Laravel

<p align="center">
<a href="https://github.com/X-Laravel/followable/actions/workflows/tests.yml"><img src="https://github.com/X-Laravel/followable/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
<a href="https://packagist.org/packages/x-laravel/followable" rel="nofollow"><img src="https://img.shields.io/packagist/v/x-laravel/followable" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/x-laravel/followable" rel="nofollow"><img src="https://img.shields.io/packagist/php-v/x-laravel/followable" alt="PHP Version"></a>
<a href="https://packagist.org/packages/x-laravel/followable" rel="nofollow"><img src="https://img.shields.io/packagist/dt/x-laravel/followable" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/x-laravel/followable" rel="nofollow"><img src="https://img.shields.io/packagist/l/x-laravel/followable" alt="License"></a>
</p>

Polymorphic follows for Eloquent. Any model can be followed and any model can follow, so users can
follow venues, artists or each other, and pages can follow too. Models that need approval, such as
private accounts, receive follow requests they accept or reject.

## Requirements

| Package version | PHP | Laravel |
|-----------------|-----|---------|
| 1.x | 8.4+ | 13 |

## Installation

```bash
composer require x-laravel/followable
php artisan vendor:publish --tag=followable-migrations
php artisan migrate
```

Publish the config to change the defaults:

```bash
php artisan vendor:publish --tag=followable-config
```

## Preparing the Models

Implement `CanFollow` and use `Follower` on the model that follows. Implement `HasFollowers` and
use `Followable` on every model that can be followed; only those models are accepted. A model can
use both:

```php
use XLaravel\Followable\Concerns\Followable;
use XLaravel\Followable\Concerns\Follower;
use XLaravel\Followable\Contracts\CanFollow;
use XLaravel\Followable\Contracts\HasFollowers;
use XLaravel\Followable\Contracts\RequiresFollowApproval;

class User extends Authenticatable implements CanFollow, HasFollowers, RequiresFollowApproval
{
    use Follower;
    use Followable;

    public function requiresFollowApproval(): bool
    {
        return $this->is_private;
    }
}

class Venue extends Model implements HasFollowers
{
    use Followable;
}
```

Implement `RequiresFollowApproval` on followable models that decide whether a new follow waits for
approval. Models without it are followed at once.

The `follower_id` and `followable_id` columns follow Laravel's morph key type, so set
`Schema::defaultMorphKeyType('uuid')` or `'ulid'` before migrating if your models use them. If the
followed models mix key types, such as integer and ULID keys, publish the migration and make
`followable_id` a string column.

## Configuration

| Key | Default | Meaning |
|-----|---------|---------|
| `follower_model` | `null` | Model `followers()`, `followRequests()` and `mutualFollowers()` return when called without one; null uses the `users` auth provider's model |
| `connection` | `null` | Connection of the follow table |
| `follow.table` | `'follows'` | Name of the follow table |
| `follow.model` | `Follow::class` | Follow model; must extend the package's |
| `prune.enabled` | `true` | Schedule the pruning of left-behind follows |
| `prune.schedule` | `'daily'` | A cron expression, or a frequency method with its arguments: `'dailyAt:03:30'`, `'twiceDaily:1,13'`, `'weeklyOn:1,04:00'` |
| `prune.chunk` | `1000` | Rows deleted by each pruning query |

## Following

```php
$user->follow($venue);                 // returns the Follow, the existing one when already followed
$user->unfollow($venue);               // removes a follow or withdraws a pending request
$user->isFollowing($venue);            // accepted follows only
$user->hasRequestedToFollow($account);
```

A model cannot follow itself. Following again keeps the existing follow as it is, so a pending
request stays pending.

## Follow Requests

When the followed model's `requiresFollowApproval()` returns true, `follow()` creates a pending
request instead:

```php
$account->followRequests()->paginate();    // followers waiting for approval
$account->hasFollowRequestFrom($user);
$account->acceptFollowRequest($user);      // true when a pending request was accepted
$account->rejectFollowRequest($user);      // true when a pending request was rejected
$account->removeFollower($user);           // removes an accepted follower
```

Requests are addressed by the follower, so the follow row itself never has to appear in a URL.
The `Follow` model also offers `accept()`, `reject()`, `isAccepted()`, `isPending()` and the
`accepted()` and `pending()` scopes.

## Listing

```php
$venue->followers()->paginate(20);        // accepted followers of the default follower model
$venue->followers(Page::class)->get();    // followers of another model
$venue->isFollowedBy($user);              // false for null, so a guest can be passed

$user->followings(Venue::class)->get();   // followed venues
$user->sentFollows;                       // every follow and request the user sent
$venue->receivedFollows;                  // every follow and request the venue received
```

Show counts and the follow state of a whole list in one query:

```php
Venue::query()
    ->withCount('followers')
    ->withFollowedBy($request->user())    // adds is_followed and is_follow_requested
    ->paginate();
```

## Mutual Follows

For models that both follow and are followed:

```php
$user->isMutuallyFollowing($other);       // both follows accepted
$user->mutualFollowers()->paginate();     // followers the user follows back

User::query()
    ->withFollowsBack($request->user())   // adds follows_back: the row follows the given model
    ->paginate();
```

## Deleting and Pruning

When a followed model or a follower is deleted, its follows and requests are deleted with it.
Soft-deleted models keep them until they are force deleted.

Deletes that skip model events, such as bulk queries, database cascades or raw SQL, leave follows
behind. The package schedules `model:prune`, daily by default, to remove follows whose followed
model or follower no longer exists. Run it by hand with:

```bash
php artisan model:prune --model="XLaravel\Followable\Models\Follow"
```

Pruning skips types that no longer resolve to a model and models on another connection.

## Reacting to Follows

Every event carries the `Follow` and is dispatched after the surrounding transaction commits:

| Event | Dispatched when |
|-------|-----------------|
| `Followed` | A follow takes effect at once |
| `FollowRequested` | A follow waits for approval |
| `FollowRequestAccepted` | A pending request is accepted; `Followed` is not dispatched again |
| `FollowRequestRejected` | The followed model rejects a pending request |
| `FollowRequestCancelled` | The follower withdraws a pending request |
| `Unfollowed` | An accepted follow is removed by either side |

Following a model that is already followed dispatches nothing. Follows removed together with their
model or follower, or by pruning, dispatch nothing.

## Testing

```bash
composer install
composer test
composer analyse
```

## License

Open source software licensed under the [MIT license](LICENSE).
