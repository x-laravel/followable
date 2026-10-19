<?php

namespace XLaravel\Followable\Tests;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use XLaravel\Followable\Events\Followed;
use XLaravel\Followable\Events\FollowRequestAccepted;
use XLaravel\Followable\Events\FollowRequestCancelled;
use XLaravel\Followable\Events\FollowRequested;
use XLaravel\Followable\Events\FollowRequestRejected;
use XLaravel\Followable\Events\Unfollowed;
use XLaravel\Followable\Models\Follow;
use XLaravel\Followable\Tests\Fixtures\User;
use XLaravel\Followable\Tests\Fixtures\Venue;

class EventsTest extends TestCase
{
    private const array EVENTS = [
        Followed::class,
        Unfollowed::class,
        FollowRequested::class,
        FollowRequestAccepted::class,
        FollowRequestRejected::class,
        FollowRequestCancelled::class,
    ];

    #[Test]
    public function a_direct_follow_dispatches_followed_once(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $venue = Venue::create(['name' => 'Zorlu PSM']);

        Event::fake(self::EVENTS);

        $follow = $ali->follow($venue);
        $ali->follow($venue);

        Event::assertDispatchedTimes(Followed::class, 1);
        Event::assertDispatched(Followed::class, fn (Followed $event) => $event->follow->is($follow));
        Event::assertNotDispatched(FollowRequested::class);
    }

    #[Test]
    public function a_request_dispatches_follow_requested(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $ayse = User::create(['name' => 'Ayşe', 'is_private' => true]);

        Event::fake(self::EVENTS);

        $ali->follow($ayse);

        Event::assertDispatchedTimes(FollowRequested::class, 1);
        Event::assertNotDispatched(Followed::class);
    }

    #[Test]
    public function accepting_a_request_dispatches_follow_request_accepted_only(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $ayse = User::create(['name' => 'Ayşe', 'is_private' => true]);
        $ali->follow($ayse);

        Event::fake(self::EVENTS);

        $ayse->acceptFollowRequest($ali);
        $ayse->acceptFollowRequest($ali);

        Event::assertDispatchedTimes(FollowRequestAccepted::class, 1);
        Event::assertNotDispatched(Followed::class);
    }

    #[Test]
    public function rejecting_a_request_dispatches_follow_request_rejected(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $ayse = User::create(['name' => 'Ayşe', 'is_private' => true]);
        $ali->follow($ayse);

        Event::fake(self::EVENTS);

        $ayse->rejectFollowRequest($ali);

        Event::assertDispatchedTimes(FollowRequestRejected::class, 1);
        Event::assertNotDispatched(FollowRequestCancelled::class);
        Event::assertNotDispatched(Unfollowed::class);
    }

    #[Test]
    public function withdrawing_a_request_dispatches_follow_request_cancelled(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $ayse = User::create(['name' => 'Ayşe', 'is_private' => true]);
        $ali->follow($ayse);

        Event::fake(self::EVENTS);

        $ali->unfollow($ayse);

        Event::assertDispatchedTimes(FollowRequestCancelled::class, 1);
        Event::assertNotDispatched(Unfollowed::class);
    }

    #[Test]
    public function unfollowing_and_removing_a_follower_dispatch_unfollowed(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $can = User::create(['name' => 'Can']);
        $venue = Venue::create(['name' => 'Zorlu PSM']);
        $ali->follow($venue);
        $can->follow($venue);

        Event::fake(self::EVENTS);

        $ali->unfollow($venue);
        $venue->removeFollower($can);

        Event::assertDispatchedTimes(Unfollowed::class, 2);
    }

    #[Test]
    public function a_rolled_back_follow_is_not_announced(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $kept = Venue::create(['name' => 'Kept']);
        $rolledBack = Venue::create(['name' => 'Rolled back']);
        $heard = [];
        Event::listen(Followed::class, function (Followed $event) use (&$heard) {
            $heard[] = $event->follow->followable_id;
        });

        DB::transaction(fn () => $ali->follow($kept));

        try {
            DB::transaction(function () use ($ali, $rolledBack) {
                $ali->follow($rolledBack);

                throw new \RuntimeException();
            });
        } catch (\RuntimeException) {
        }

        $this->assertEquals([$kept->id], $heard);
    }

    #[Test]
    public function pruning_dispatches_nothing(): void
    {
        User::create(['name' => 'Ali'])->follow(Venue::create(['name' => 'Zorlu PSM']));
        DB::table('venues')->delete();

        Event::fake(self::EVENTS);

        Artisan::call('model:prune', ['--model' => [Follow::class]]);

        $this->assertSame(0, Follow::query()->count());
        Event::assertNothingDispatched();
    }
}
