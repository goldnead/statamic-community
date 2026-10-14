<?php

namespace Goldnead\Community\Actions;

use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Policies\CommunityPolicy;
use Statamic\Actions\Action;
use Statamic\Facades\User;

/**
 * The moderation steps as native Statamic actions, so the row menu and the
 * bulk bar of the listing share one mechanism, with core's confirmation,
 * validation, toast and authorization.
 *
 * `visibleTo()` keeps them off every other listing: core asks every
 * registered action about every item, and one that answers `true` to
 * anything would appear on the Entries screen.
 *
 * Every step goes through the services, which authorise through the policy a
 * second time; `authorize()` here is the first lock.
 */
abstract class ModerationAction extends Action
{
    public function authorize($user, $item)
    {
        return (bool) $user?->can(CommunityPolicy::MODERATE);
    }

    protected function isContent(mixed $item): bool
    {
        return $item instanceof Post || $item instanceof Comment;
    }

    protected function moderator(): mixed
    {
        return User::current();
    }
}
