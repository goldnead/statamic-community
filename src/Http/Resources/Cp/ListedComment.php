<?php

namespace Goldnead\Community\Http\Resources\Cp;

use Goldnead\Community\Models\Comment;
use Goldnead\Community\Support\Author;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Statamic\Facades\Action;

/**
 * One comment row. Display name and type of the author only.
 *
 * @mixin Comment
 */
class ListedComment extends JsonResource
{
    public function toArray($request)
    {
        /** @var Comment $comment */
        $comment = $this->resource;

        return [
            'id' => $comment->id,
            'excerpt' => Str::limit(trim(strip_tags($comment->body)), 140),
            'post' => $comment->post?->title ?: Str::limit(trim(strip_tags((string) $comment->post?->body)), 60),
            'room' => $comment->post?->room?->name,
            'region' => $comment->post?->region,
            'author' => Author::name($comment),
            'author_type' => Author::type($comment),
            'is_reply' => $comment->parent_id !== null,
            'hidden' => $comment->hidden,
            'hidden_reason' => $comment->hidden_reason,
            'created_at' => $comment->created_at?->toIso8601String(),
            'actions' => Action::for($comment, ['type' => 'comment']),
        ];
    }
}
