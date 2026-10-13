<?php

namespace Goldnead\Community\Services;

use Goldnead\Community\Events\CommentCreated;
use Goldnead\Community\Exceptions\CommunityException;
use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Models\Reaction;
use Goldnead\Community\Support\Sanitizer;
use Goldnead\Teams\Models\Team;
use Goldnead\Teams\Support\Users;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CommentService
{
    public function __construct(protected Sanitizer $sanitizer) {}

    /** One level of replies: a reply to a reply attaches to the top comment. */
    public function create(Post $post, mixed $user, string $body, ?Comment $parent = null, ?Team $team = null): Comment
    {
        Gate::forUser($user)->authorize('comment', [Comment::class, $post, $team]);

        $comment = Comment::create([
            'post_id' => $post->id,
            'parent_id' => $parent && $parent->post_id === $post->id ? ($parent->parent_id ?? $parent->id) : null,
            'author_user_id' => Users::key($user),
            'author_team_id' => $team?->id,
            'body' => $this->clean($body),
        ]);

        CommentCreated::dispatch($comment);

        return $comment;
    }

    public function update(Comment $comment, mixed $user, string $body): Comment
    {
        Gate::forUser($user)->authorize('update', $comment);

        $comment->update(['body' => $this->clean($body)]);

        return $comment;
    }

    public function delete(Comment $comment, mixed $user): void
    {
        Gate::forUser($user)->authorize('delete', $comment);

        DB::transaction(function () use ($comment) {
            $ids = Comment::where('parent_id', $comment->id)->pluck('id')->push($comment->id);
            Reaction::where('target_type', Comment::TYPE)->whereIn('target_id', $ids)->delete();
            $comment->delete();
        });
    }

    protected function clean(string $body): string
    {
        $body = $this->sanitizer->clean($body);

        return trim(strip_tags($body)) === '' ? throw CommunityException::emptyBody() : $body;
    }
}
