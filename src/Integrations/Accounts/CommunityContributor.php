<?php

namespace Goldnead\Community\Integrations\Accounts;

use Goldnead\Accounts\Contracts\ContributesPersonalData;
use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Models\Reaction;
use Goldnead\Community\Models\RoomMembership;
use Illuminate\Support\Facades\DB;
use Statamic\Auth\User;

/**
 * Only tagged by the service provider when goldnead/statamic-accounts is there.
 *
 * Erasure: everything the account wrote goes, including what others wrote
 * below it (comments on its posts, replies to its comments) and the
 * reactions to those. A thread cannot outlive its author's text.
 */
class CommunityContributor implements ContributesPersonalData, ErasesPersonalData
{
    public function key(): string
    {
        return 'community';
    }

    public function label(): string
    {
        return 'Community';
    }

    public function available(): bool
    {
        return true;
    }

    public function collect(User $user): array
    {
        $id = (string) $user->id();

        return [
            'posts' => Post::where('author_user_id', $id)->get(['id', 'title', 'body', 'created_at'])->toArray(),
            'comments' => Comment::where('author_user_id', $id)->get(['id', 'post_id', 'body', 'created_at'])->toArray(),
            'reactions' => Reaction::where('user_id', $id)->get(['target_type', 'target_id', 'kind'])->toArray(),
            'rooms' => RoomMembership::where('user_id', $id)->pluck('room_id')->all(),
        ];
    }

    public function blockers(User $user, string $audience = 'customer'): array
    {
        return [];
    }

    public function erase(User $user): ErasureResult
    {
        $id = (string) $user->id();

        return DB::transaction(function () use ($id) {
            $postIds = Post::where('author_user_id', $id)->pluck('id');
            // Own comments, replies to them, and every comment under an own post.
            $own = Comment::where('author_user_id', $id)->orWhereIn('post_id', $postIds)->pluck('id');
            $commentIds = $own->merge(Comment::whereIn('parent_id', $own)->pluck('id'))->unique();

            $reactions = Reaction::where('user_id', $id)
                ->orWhere(fn ($q) => $q->where('target_type', Post::TYPE)->whereIn('target_id', $postIds))
                ->orWhere(fn ($q) => $q->where('target_type', Comment::TYPE)->whereIn('target_id', $commentIds))
                ->delete();
            $comments = Comment::whereIn('id', $commentIds)->delete();
            $posts = Post::whereIn('id', $postIds)->delete();
            $rooms = RoomMembership::where('user_id', $id)->delete();

            return new ErasureResult($this->key(), deleted: array_filter(compact('posts', 'comments', 'reactions', 'rooms')));
        });
    }
}
