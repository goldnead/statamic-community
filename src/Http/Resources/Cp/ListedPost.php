<?php

namespace Goldnead\Community\Http\Resources\Cp;

use Goldnead\Community\Models\Post;
use Goldnead\Community\Support\Author;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Statamic\Facades\Action;

/**
 * One post row. The author is a display name and a type, never an address or
 * an account id.
 *
 * @mixin Post
 */
class ListedPost extends JsonResource
{
    public function toArray($request)
    {
        /** @var Post $post */
        $post = $this->resource;

        return [
            'id' => $post->id,
            'title' => $post->title !== '' ? $post->title : Str::limit(trim(strip_tags($post->body)), 60),
            'excerpt' => Str::limit(trim(strip_tags($post->body)), 140),
            'room' => $post->room?->name,
            'region' => $post->region,
            'author' => Author::name($post),
            'author_type' => Author::type($post),
            'status' => $post->status,
            'hidden' => $post->hidden,
            'hidden_reason' => $post->hidden_reason,
            'comments' => $post->getAttribute('comments_count'),
            'published_at' => $post->published_at?->toIso8601String(),
            'created_at' => $post->created_at?->toIso8601String(),
            // The row menu of the Listing: what this user may do to this post.
            'actions' => Action::for($post, ['type' => 'post']),
        ];
    }
}
