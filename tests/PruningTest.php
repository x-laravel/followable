<?php

namespace XLaravel\Followable\Tests;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use XLaravel\Followable\Models\Follow;
use XLaravel\Followable\Tests\Fixtures\User;
use XLaravel\Followable\Tests\Fixtures\Venue;

class PruningTest extends TestCase
{
    public function test_follows_of_models_that_no_longer_exist_are_pruned(): void
    {
        $ali = User::query()->create(['name' => 'Ali']);
        $gone = Venue::query()->create(['name' => 'Gone']);
        $kept = Venue::query()->create(['name' => 'Kept']);
        $trashed = User::query()->create(['name' => 'Trashed']);
        $ali->follow($gone);
        $ali->follow($kept);
        $ali->follow($trashed);
        DB::table('venues')->where('id', $gone->id)->delete();
        $trashed->delete();

        $this->prune();

        $this->assertSame(0, $gone->receivedFollows()->count());
        $this->assertSame(1, $kept->receivedFollows()->count());
        $this->assertSame(1, $trashed->receivedFollows()->count());
    }

    public function test_follows_of_followers_that_no_longer_exist_are_pruned(): void
    {
        $gone = User::query()->create(['name' => 'Ali']);
        $kept = User::query()->create(['name' => 'Ayşe']);
        $venue = Venue::query()->create(['name' => 'Zorlu PSM']);
        $gone->follow($venue);
        $kept->follow($venue);
        DB::table('users')->where('id', $gone->id)->delete();

        $this->prune();

        $this->assertEquals([$kept->id], $venue->receivedFollows()->pluck('follower_id')->all());
    }

    public function test_unknown_types_are_left_alone(): void
    {
        $ali = User::query()->create(['name' => 'Ali']);
        Follow::query()->create(['follower_type' => $ali->getMorphClass(), 'follower_id' => $ali->id, 'followable_type' => 'missing', 'followable_id' => 1]);

        $this->prune();

        $this->assertSame(1, Follow::query()->count());
    }

    public function test_pruning_is_scheduled_daily_in_chunks_of_a_thousand(): void
    {
        $event = $this->scheduledPruning();

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertStringContainsString('--chunk=1000', (string) $event->command);
    }

    public function test_the_chunk_size_can_be_configured(): void
    {
        config(['followable.prune.chunk' => 250]);

        $this->assertStringContainsString('--chunk=250', (string) $this->scheduledPruning()?->command);
    }

    public function test_the_schedule_accepts_a_cron_expression(): void
    {
        config(['followable.prune.schedule' => '30 3 * * *']);

        $this->assertSame('30 3 * * *', $this->scheduledPruning()?->expression);
    }

    public function test_the_schedule_accepts_a_frequency_with_arguments(): void
    {
        config(['followable.prune.schedule' => 'weeklyOn:1,04:00']);

        $this->assertSame('0 4 * * 1', $this->scheduledPruning()?->expression);
    }

    public function test_an_unknown_frequency_is_refused(): void
    {
        config(['followable.prune.schedule' => 'sometimes']);

        $this->expectException(InvalidArgumentException::class);

        $this->scheduledPruning();
    }

    public function test_the_schedule_can_be_turned_off(): void
    {
        config(['followable.prune.enabled' => false]);

        $this->assertNull($this->scheduledPruning());
    }

    protected function prune(): void
    {
        $this->assertSame(0, Artisan::call('model:prune', ['--model' => [Follow::class]]));
    }

    protected function scheduledPruning(): ?ScheduledEvent
    {
        $this->app?->forgetInstance(Schedule::class);

        $events = $this->app?->make(Schedule::class)->events() ?? [];

        foreach ($events as $event) {
            if (str_contains((string) $event->command, 'model:prune') && str_contains((string) $event->command, 'Follow')) {
                return $event;
            }
        }

        return null;
    }
}
