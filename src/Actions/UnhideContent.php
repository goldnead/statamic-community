<?php

namespace Goldnead\Community\Actions;

use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Services\PostService;

class UnhideContent extends ModerationAction
{
    protected static $handle = 'community_unhide';

    protected $confirm = false;

    public static function title()
    {
        return __('Show again');
    }

    public function icon(): string
    {
        return 'eye';
    }

    public function visibleTo($item)
    {
        return $this->isContent($item) && $item->getAttribute('hidden');
    }

    public function buttonText()
    {
        return __('Show again');
    }

    public function run($items, $values)
    {
        $service = app(PostService::class);
        $items->each(fn (Post|Comment $item) => $service->hide($item, $this->moderator(), false));

        return trans_choice('{1} Shown again|[2,*] :count items shown again', $items->count());
    }
}
