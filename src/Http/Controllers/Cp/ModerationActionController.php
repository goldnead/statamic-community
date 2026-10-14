<?php

namespace Goldnead\Community\Http\Controllers\Cp;

use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Statamic\Http\Controllers\CP\ActionController;

/**
 * The two endpoints core's `<Listing>` talks to for row and bulk actions. All
 * this class supplies is the lookup from a checked id back to a model; the
 * listing says in its `context` whether the ids are posts or comments.
 */
class ModerationActionController extends ActionController
{
    protected function getSelectedItems($items, $context)
    {
        $selected = collect($items)->unique();
        $model = ($context['type'] ?? null) === 'comment' ? Comment::class : Post::class;

        $found = $model::query()->whereKey($selected->all())->get();

        // An id that resolves to nothing is gone, and no selection to quietly
        // shrink: `Action::visibleToBulk()` decides by comparing counts, so an
        // empty collection would satisfy every action in the Control Panel.
        abort_unless($found->count() === $selected->count() && $found->isNotEmpty(), 404);

        return $found;
    }
}
