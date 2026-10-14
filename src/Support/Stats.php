<?php

namespace Goldnead\Community\Support;

use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;

/** The numbers on the dashboard widget. Counts only, nothing personal. */
class Stats
{
    /**
     * @return array{posts: int, comments: int, pending: int, hidden: int}
     */
    public static function summary(): array
    {
        $since = now()->subDays(7);

        return [
            'posts' => Post::query()->where('created_at', '>=', $since)->count(),
            'comments' => Comment::query()->where('created_at', '>=', $since)->count(),
            'pending' => Post::query()->where('status', Post::PENDING)->count(),
            'hidden' => Post::query()->where('hidden', true)->count() + Comment::query()->where('hidden', true)->count(),
        ];
    }
}
