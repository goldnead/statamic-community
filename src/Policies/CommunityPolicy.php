<?php

namespace Goldnead\Community\Policies;

use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Goldnead\Teams\Models\Team;
use Goldnead\Teams\Support\Users;
use Goldnead\Teams\TeamsManager;

/**
 * Registered for {@see Post} and {@see Comment}. Writing needs a confirmed
 * account, writing as a team needs the team permission `post as team`
 * (goldnead/statamic-teams). Editing is for the author only, and only while the account is confirmed; deleting is
 * for the author and for moderators (`moderate community`).
 */
class CommunityPolicy
{
    public const TEAM_PERMISSION = 'post as team';

    public const MODERATE = 'moderate community';

    public function post(mixed $user, ?Team $team = null): bool
    {
        return $this->confirmed($user) && $this->mayActAs($user, $team);
    }

    public function comment(mixed $user, Post $post, ?Team $team = null): bool
    {
        return $post->isVisible() && $this->post($user, $team);
    }

    public function react(mixed $user, Post|Comment $target): bool
    {
        $visible = $target instanceof Post ? $target->isVisible() : (! $target->getAttribute('hidden') && $target->post->isVisible());

        return $visible && $this->confirmed($user);
    }

    public function update(mixed $user, Post|Comment $target): bool
    {
        return $this->confirmed($user) && $this->own($user, $target);
    }

    public function delete(mixed $user, Post|Comment $target): bool
    {
        return $this->own($user, $target) || $this->moderate($user);
    }

    public function moderate(mixed $user): bool
    {
        return (bool) Users::find($user)?->can(self::MODERATE);
    }

    protected function own(mixed $user, Post|Comment $target): bool
    {
        $key = Users::key($user);

        return $key !== null && $target->author_user_id === $key;
    }

    protected function mayActAs(mixed $user, ?Team $team): bool
    {
        return $team === null || app(TeamsManager::class)->can($user, $team, self::TEAM_PERMISSION);
    }

    protected function confirmed(mixed $user): bool
    {
        $account = Users::find($user);

        if ($account === null) {
            return false;
        }

        if (! config('community.require_confirmed') || ! config('accounts.verification.enabled', true)) {
            return true;
        }

        $field = config('community.confirmed_field') ?? config('accounts.verification.field', 'email_verified_at');

        return filled($account->get($field));
    }
}
