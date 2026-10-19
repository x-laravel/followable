<?php

namespace XLaravel\Followable\Tests;

use PHPUnit\Framework\Attributes\Test;
use XLaravel\Followable\Models\Follow;
use XLaravel\Followable\Tests\Fixtures\Page;
use XLaravel\Followable\Tests\Fixtures\User;
use XLaravel\Followable\Tests\Fixtures\Venue;

class FollowableTest extends TestCase
{
    #[Test]
    public function followers_returns_accepted_followers_of_the_default_model(): void
    {
        $ayse = User::create(['name' => 'Ayşe', 'is_private' => true]);
        $ali = User::create(['name' => 'Ali']);
        $ali->follow($ayse);
        $ayse->acceptFollowRequest($ali);
        User::create(['name' => 'Can'])->follow($ayse);
        Page::create(['name' => 'Fan Page'])->follow($ayse);

        $this->assertSame(['Ali'], $ayse->followers()->pluck('name')->all());
        $this->assertSame(['Can'], $ayse->followRequests()->pluck('name')->all());
    }

    #[Test]
    public function followers_can_return_another_follower_model(): void
    {
        $venue = Venue::create(['name' => 'Zorlu PSM']);
        User::create(['name' => 'Ali'])->follow($venue);
        Page::create(['name' => 'Fan Page'])->follow($venue);

        $this->assertSame(['Fan Page'], $venue->followers(Page::class)->pluck('name')->all());
    }

    #[Test]
    public function the_default_follower_model_can_be_configured(): void
    {
        config(['followable.follower_model' => Page::class]);
        $venue = Venue::create(['name' => 'Zorlu PSM']);
        User::create(['name' => 'Ali'])->follow($venue);
        Page::create(['name' => 'Fan Page'])->follow($venue);

        $this->assertSame(['Fan Page'], $venue->followers()->pluck('name')->all());
    }

    #[Test]
    public function followers_and_requests_can_be_counted(): void
    {
        $ayse = User::create(['name' => 'Ayşe', 'is_private' => true]);
        $ali = User::create(['name' => 'Ali']);
        $ali->follow($ayse);
        $ayse->acceptFollowRequest($ali);
        User::create(['name' => 'Can'])->follow($ayse);

        $ayse = User::query()->whereKey($ayse->id)->withCount(['followers', 'followRequests'])->firstOrFail();

        $this->assertSame(1, $ayse->getAttribute('followers_count'));
        $this->assertSame(1, $ayse->getAttribute('follow_requests_count'));
    }

    #[Test]
    public function a_follow_request_can_be_accepted(): void
    {
        $ayse = User::create(['name' => 'Ayşe', 'is_private' => true]);
        $ali = User::create(['name' => 'Ali']);
        $ali->follow($ayse);

        $this->assertTrue($ayse->hasFollowRequestFrom($ali));
        $this->assertTrue($ayse->acceptFollowRequest($ali));
        $this->assertFalse($ayse->acceptFollowRequest($ali));
        $this->assertTrue($ayse->isFollowedBy($ali));
        $this->assertFalse($ayse->hasFollowRequestFrom($ali));
    }

    #[Test]
    public function a_follow_request_can_be_rejected(): void
    {
        $ayse = User::create(['name' => 'Ayşe', 'is_private' => true]);
        $ali = User::create(['name' => 'Ali']);
        $ali->follow($ayse);

        $this->assertTrue($ayse->rejectFollowRequest($ali));
        $this->assertFalse($ayse->rejectFollowRequest($ali));
        $this->assertSame(0, Follow::query()->count());
    }

    #[Test]
    public function rejecting_does_not_remove_an_accepted_follower(): void
    {
        $venue = Venue::create(['name' => 'Zorlu PSM']);
        $ali = User::create(['name' => 'Ali']);
        $ali->follow($venue);

        $this->assertFalse($venue->rejectFollowRequest($ali));
        $this->assertTrue($venue->isFollowedBy($ali));
    }

    #[Test]
    public function a_follower_can_be_removed(): void
    {
        $venue = Venue::create(['name' => 'Zorlu PSM']);
        $ali = User::create(['name' => 'Ali']);
        $ali->follow($venue);

        $this->assertTrue($venue->removeFollower($ali));
        $this->assertFalse($venue->removeFollower($ali));
        $this->assertFalse($venue->isFollowedBy($ali));
    }

    #[Test]
    public function requests_are_matched_by_the_followed_type(): void
    {
        $ayse = User::create(['name' => 'Ayşe', 'is_private' => true]);
        $ali = User::create(['name' => 'Ali']);
        $venue = Venue::create(['name' => 'Zorlu PSM']);
        $this->assertSame($ayse->id, $venue->id);
        $ali->follow($venue);

        $this->assertFalse($ayse->rejectFollowRequest($ali));
        $this->assertTrue($ali->isFollowing($venue));
    }

    #[Test]
    public function is_followed_by_returns_false_for_null(): void
    {
        $venue = Venue::create(['name' => 'Zorlu PSM']);

        $this->assertFalse($venue->isFollowedBy(null));
        $this->assertFalse($venue->hasFollowRequestFrom(null));
    }

    #[Test]
    public function mutual_followers_are_followers_followed_back(): void
    {
        $me = User::create(['name' => 'Me']);
        $friend = User::create(['name' => 'Friend']);
        $fan = User::create(['name' => 'Fan']);
        $private = User::create(['name' => 'Private', 'is_private' => true]);
        $friend->follow($me);
        $me->follow($friend);
        $fan->follow($me);
        $private->follow($me);
        $me->follow($private);

        $this->assertSame(['Friend'], $me->mutualFollowers()->pluck('name')->all());
    }

    #[Test]
    public function with_followed_by_adds_the_follow_state_in_one_query(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $followed = User::create(['name' => 'Followed']);
        $requested = User::create(['name' => 'Requested', 'is_private' => true]);
        User::create(['name' => 'Other']);
        $ali->follow($followed);
        $ali->follow($requested);

        $states = User::query()
            ->whereKeyNot($ali->id)
            ->withFollowedBy($ali)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (User $user) => [$user->name => [(bool) $user->getAttribute('is_followed'), (bool) $user->getAttribute('is_follow_requested')]])
            ->all();
        $guest = User::query()->withFollowedBy(null)->pluck('is_followed')->map(fn (mixed $state) => (bool) $state)->unique()->all();

        $this->assertSame(['Followed' => [true, false], 'Requested' => [false, true], 'Other' => [false, false]], $states);
        $this->assertSame([false], $guest);
    }

    #[Test]
    public function deleting_the_model_deletes_its_follows(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $venue = Venue::create(['name' => 'Zorlu PSM']);
        $other = Venue::create(['name' => 'Harbiye']);
        $ali->follow($venue);
        $ali->follow($other);

        $venue->delete();

        $this->assertSame([$other->id], Follow::query()->pluck('followable_id')->all());
    }

    #[Test]
    public function soft_deleting_the_model_keeps_its_follows(): void
    {
        $ayse = User::create(['name' => 'Ayşe']);
        User::create(['name' => 'Ali'])->follow($ayse);

        $ayse->delete();

        $this->assertSame(1, Follow::query()->count());
    }
}
