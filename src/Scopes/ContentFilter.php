<?php

namespace Goldnead\Community\Scopes;

use Closure;
use Goldnead\Community\Http\Controllers\Cp\CommentController;
use Goldnead\Community\Http\Controllers\Cp\PostController;
use Goldnead\Community\Models\Comment;
use Statamic\Query\Scopes\Filter;

/**
 * Shared by the filters of the post and the comment listing. A comment has no
 * room, region or status of its own, it has its post's: for a comment query
 * those conditions are applied to the post it belongs to.
 */
abstract class ContentFilter extends Filter
{
    public $pinned = true;

    public function visibleTo($key)
    {
        return in_array($key, [PostController::SCOPE, CommentController::SCOPE], true);
    }

    /**
     * @param  Closure(mixed): mixed  $condition  A condition on posts.
     */
    protected function onPost(mixed $query, Closure $condition): void
    {
        $query->getModel() instanceof Comment
            ? $query->whereHas('post', fn ($post) => $condition($post))
            : $condition($query);
    }

    /** @param  array<string, mixed>  $values */
    protected function value(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
