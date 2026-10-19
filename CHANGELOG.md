# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-19

### Added

- `Contracts\CanFollow` and `Concerns\Follower` for the model that follows,
  `Contracts\HasFollowers` and `Concerns\Followable` for the models that can be followed; one model
  can use both.
- Polymorphic followers, so any model can follow; a follower follows a model once and cannot
  follow itself.
- `Contracts\RequiresFollowApproval` for models that decide whether a new follow waits for
  approval, with `acceptFollowRequest()`, `rejectFollowRequest()` and `hasFollowRequestFrom()`.
- `follow()`, `unfollow()`, `isFollowing()`, `hasRequestedToFollow()` and `removeFollower()`.
- `followers()` and `followRequests()` with a configurable default follower model, `followings()`,
  `sentFollows()` and `receivedFollows()` for listing and counting.
- `isMutuallyFollowing()`, `mutualFollowers()` and the `withFollowsBack()` scope for mutual follows.
- `withFollowedBy()` scope and `isFollowedBy()` to show the follow state of a list in one query.
- Cleanup of follows when their model or follower is deleted, and pruning of left-behind follows
  through `model:prune`, scheduled by the package with a configurable frequency and chunk size.
- `Events\Followed`, `Events\Unfollowed`, `Events\FollowRequested`, `Events\FollowRequestAccepted`,
  `Events\FollowRequestRejected` and `Events\FollowRequestCancelled`, dispatched after commit.
- Configurable connection, table and model.

[1.0.0]: https://github.com/x-laravel/followable/releases/tag/v1.0.0
