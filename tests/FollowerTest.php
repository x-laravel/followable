<?php

namespace XLaravel\Followable\Tests;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use XLaravel\Followable\Models\Follow;
use XLaravel\Followable\Tests\Fixtures\Guest;
use XLaravel\Followable\Tests\Fixtures\User;
use XLaravel\Followable\Tests\Fixtures\Venue;

class FollowerTest extends TestCase
{
    #[Test]
    public function following_a_public_model_takes_effect_at_once(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $venue = Venue::create(['name' => 'Zorlu PSM']);

        $follow = $ali->follow($venue);

        $this->assertTrue($follow->isAccepted());
        $this->assertTrue($follow->follower()->is($ali));
        $this->assertTrue($follow->followable()->is($venue));
        $this->assertTrue($ali->isFollowing($venue));
        $this->assertFalse($ali->hasRequestedToFollow($venue));
    }

    #[Test]
    public function following_a_model_that_requires_approval_sends_a_request(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $ayse = User::create(['name' => 'Ayşe', 'is_private' => true]);

        $follow = $ali->follow($ayse);

        $this->assertTrue($follow->isPending());
        $this->assertFalse($ali->isFollowing($ayse));
        $this->assertTrue($ali->hasRequestedToFollow($ayse));
    }

    #[Test]
    public function following_twice_keeps_one_follow(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $venue = Venue::create(['name' => 'Zorlu PSM']);

        $first = $ali->follow($venue);
        $second = $ali->follow($venue);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Follow::query()->count());
    }

    #[Test]
    public function following_again_keeps_a_pending_request_pending(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $ayse = User::create(['name' => 'Ayşe', 'is_private' => true]);
        $ali->follow($ayse);
        $ayse->update(['is_private' => false]);

        $this->assertTrue($ali->follow($ayse)->isPending());
    }

    #[Test]
    public function a_model_cannot_follow_itself(): void
    {
        $ali = User::create(['name' => 'Ali']);

        $this->expectException(InvalidArgumentException::class);

        $ali->follow($ali);
    }

    #[Test]
    public function a_model_without_can_follow_cannot_follow(): void
    {
        $guest = Guest::create(['name' => 'Guest']);

        $this->expectException(LogicException::class);

        $guest->follow(Venue::create(['name' => 'Zorlu PSM']));
    }

    #[Test]
    public function unfollow_removes_a_follow_or_a_pending_request(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $venue = Venue::create(['name' => 'Zorlu PSM']);
        $ayse = User::create(['name' => 'Ayşe', 'is_private' => true]);
        $ali->follow($venue);
        $ali->follow($ayse);

        $this->assertTrue($ali->unfollow($venue));
        $this->assertTrue($ali->unfollow($ayse));
        $this->assertFalse($ali->unfollow($venue));
        $this->assertSame(0, Follow::query()->count());
    }

    #[Test]
    public function followings_returns_the_followed_models_of_a_type(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $followed = Venue::create(['name' => 'Zorlu PSM']);
        Venue::create(['name' => 'Harbiye']);
        $ali->follow($followed);
        $ali->follow(User::create(['name' => 'Ayşe']));
        $ali->follow(User::create(['name' => 'Can', 'is_private' => true]));

        $this->assertSame(['Zorlu PSM'], $ali->followings(Venue::class)->pluck('name')->all());
        $this->assertSame(['Ayşe'], $ali->followings(User::class)->pluck('name')->all());
    }

    #[Test]
    public function is_mutually_following_needs_both_follows_accepted(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $ayse = User::create(['name' => 'Ayşe', 'is_private' => true]);
        $ali->follow($ayse);
        $ayse->follow($ali);

        $this->assertFalse($ali->isMutuallyFollowing($ayse));

        $ayse->acceptFollowRequest($ali);

        $this->assertTrue($ali->isMutuallyFollowing($ayse));
        $this->assertTrue($ayse->isMutuallyFollowing($ali));
    }

    #[Test]
    public function with_follows_back_tells_which_models_follow_the_given_one(): void
    {
        $me = User::create(['name' => 'Me']);
        User::create(['name' => 'Ali'])->follow($me);
        User::create(['name' => 'Ayşe']);

        $states = User::query()->whereKeyNot($me->id)->withFollowsBack($me)->orderBy('id')->pluck('follows_back', 'name')->map(fn (mixed $state) => (bool) $state)->all();
        $guest = User::query()->withFollowsBack(null)->pluck('follows_back')->map(fn (mixed $state) => (bool) $state)->all();

        $this->assertSame(['Ali' => true, 'Ayşe' => false], $states);
        $this->assertSame([false, false, false], $guest);
    }

    #[Test]
    public function deleting_the_follower_deletes_its_follows(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $ayse = User::create(['name' => 'Ayşe']);
        $venue = Venue::create(['name' => 'Zorlu PSM']);
        $ali->follow($venue);
        $ayse->follow($venue);

        $ali->forceDelete();

        $this->assertSame([$ayse->id], Follow::query()->pluck('follower_id')->all());
    }

    #[Test]
    public function soft_deleting_the_follower_keeps_its_follows(): void
    {
        $ali = User::create(['name' => 'Ali']);
        $ali->follow(Venue::create(['name' => 'Zorlu PSM']));

        $ali->delete();

        $this->assertSame(1, Follow::query()->count());
    }

    #[Test]
    public function a_model_can_both_follow_and_be_followed(): void
    {
        $fan = User::create(['name' => 'Ali']);
        $star = User::create(['name' => 'Ayşe']);

        $fan->follow($star);

        $this->assertSame(1, $fan->sentFollows()->count());
        $this->assertSame(1, $star->receivedFollows()->count());
        $this->assertSame(0, $fan->receivedFollows()->count());
        $this->assertSame(0, $star->sentFollows()->count());
    }
}
