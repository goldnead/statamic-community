<?php

namespace Goldnead\Community\Actions;

use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Services\CommentService;
use Goldnead\Community\Services\PostService;

/** Deletes for good, with the comments and reactions that hang on it. */
class DeleteContent extends ModerationAction
{
    protected static $handle = 'community_delete';

    protected $dangerous = true;

    public static function title()
    {
        return __('Delete');
    }

    public function icon(): string
    {
        return 'trash';
    }

    public function visibleTo($item)
    {
        return $this->isContent($item);
    }

    public function buttonText()
    {
        return trans_choice('{1} Delete item|[2,*] Delete :count items', $this->items->count());
    }

    public function confirmationText()
    {
        return trans_choice('{1} Are you sure you want to delete this item? Its replies and reactions go with it. This cannot be undone.|[2,*] Are you sure you want to delete these :count items? Their replies and reactions go with them. This cannot be undone.', $this->items->count());
    }

    public function run($items, $values)
    {
        $moderator = $this->moderator();

        $items->each(fn (Post|Comment $item) => $item instanceof Post
            ? app(PostService::class)->delete($item, $moderator)
            : app(CommentService::class)->delete($item, $moderator));

        return trans_choice('{1} Deleted|[2,*] :count items deleted', $items->count());
    }
}
