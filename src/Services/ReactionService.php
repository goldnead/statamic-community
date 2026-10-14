<?php

namespace Goldnead\Community\Services;

use Goldnead\Community\Exceptions\CommunityException;
use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Models\Reaction;
use Goldnead\Teams\Support\Users;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;

class ReactionService
{
    /** One reaction per account and target; reacting again changes its kind. */
    public function react(Post|Comment $target, mixed $user, string $kind = 'like'): Reaction
    {
        Gate::forUser($user)->authorize('react', $target);
        in_array($kind, config('community.reactions'), true) || throw CommunityException::unknown('reaction', $kind);

        $key = ['user_id' => Users::key($user), 'target_type' => $this->type($target), 'target_id' => $target->id];

        try {
            return Reaction::updateOrCreate($key, ['kind' => $kind]);
        } catch (UniqueConstraintViolationException $e) {
            // A double click: the other request inserted between our lookup
            // and our insert. Its row is the reaction; ours only sets the kind.
            // A locking read: inside a surrounding transaction a plain one can
            // still see the snapshot from before the other insert.
            $existing = Reaction::where($key)->lockForUpdate()->first() ?? throw $e;
            $existing->update(['kind' => $kind]);

            return $existing;
        }
    }

    public function remove(Post|Comment $target, mixed $user): void
    {
        Reaction::where('user_id', Users::key($user))->where('target_type', $this->type($target))->where('target_id', $target->id)->delete();
    }

    protected function type(Post|Comment $target): string
    {
        return $target instanceof Post ? Post::TYPE : Comment::TYPE;
    }
}
