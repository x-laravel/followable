<?php

namespace XLaravel\Followable\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use XLaravel\Followable\Models\Follow;

/**
 * @internal
 */
final class FollowableConfig
{
    /**
     * @return class-string<Follow>
     */
    public static function followModel(): string
    {
        $model = Config::get('followable.follow.model') ?? Follow::class;

        if (! is_string($model) || ! is_a($model, Follow::class, true)) {
            throw new InvalidArgumentException('Set followable.follow.model to a model that extends ' . Follow::class . '.');
        }

        return $model;
    }

    /**
     * @return class-string<Model>
     */
    public static function followerModel(): string
    {
        $model = Config::get('followable.follower_model') ?? Config::get('auth.providers.users.model');

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException('Set followable.follower_model to an Eloquent model.');
        }

        return $model;
    }

    public static function followTable(): string
    {
        return self::string('follow.table') ?: 'follows';
    }

    public static function connection(): ?string
    {
        return self::string('connection') ?: null;
    }

    protected static function string(string $key): string
    {
        $value = Config::get("followable.{$key}");

        return is_string($value) ? trim($value) : '';
    }
}
