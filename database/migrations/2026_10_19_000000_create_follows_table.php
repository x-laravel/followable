<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use XLaravel\Followable\Support\FollowableConfig;

return new class extends Migration
{
    public function up(): void
    {
        $follows = FollowableConfig::followTable();

        Schema::connection($this->getConnection())->create($follows, function (Blueprint $table) use ($follows) {
            $table->id();
            $table->morphs('follower');
            $table->morphs('followable');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['follower_type', 'follower_id', 'followable_type', 'followable_id'], "{$follows}_follower_followable_unique");
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists(FollowableConfig::followTable());
    }

    public function getConnection(): ?string
    {
        return FollowableConfig::connection();
    }
};
