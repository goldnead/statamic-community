<?php

namespace Goldnead\Community\Support;

use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Goldnead\Teams\Models\Team;
use Goldnead\Teams\Support\Users;
use Statamic\Auth\User;

/**
 * How the Control Panel names an author: the display name, nothing else.
 *
 * Deliberately not `Users::name()`, which falls back to the e-mail address
 * when an account has no name. A moderation screen needs to tell members
 * apart, not to know where they live, and it is the screen most likely to be
 * shared in a call.
 */
class Author
{
    public static function name(Post|Comment $content): string
    {
        if ($content->author_team_id !== null) {
            $team = $content->relationLoaded('team') ? $content->getRelation('team') : Team::query()->find($content->author_team_id);

            return $team instanceof Team ? $team->name : __('Deleted team');
        }

        $user = Users::find($content->author_user_id);
        $name = $user instanceof User ? $user->get('name') : null;

        return is_string($name) && trim($name) !== '' ? trim($name) : __('Member');
    }

    public static function type(Post|Comment $content): string
    {
        return $content->author_team_id === null ? 'person' : 'team';
    }
}
