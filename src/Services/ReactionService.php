<?php

namespace Goldnead\Community\Services;

use Goldnead\Community\Exceptions\CommunityException;
use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Models\Reaction;
use Goldnead\Teams\Support\Users;
use Illuminate\Support\Facades\Gate;

class ReactionService
{
    /** One reaction per account and target; reacting again changes its kind. */
    public function react(Post|Comment $target, mixed $user, string $kind = 'like'): Reaction
    {
        Gate::forUser($user)->authorize('react', $target);
        in_array($kind, config('community.reactions'), true) || throw CommunityException::unknown('reaction', $kind);

        return Reaction::updateOrCreate(
            ['user_id' => Users::key($user), 'target_type' => $this->type($target), 'target_id' => $target->id],
            ['kind' => $kind],
        );
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
