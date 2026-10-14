<?php

namespace Goldnead\Community\Actions;

use Goldnead\Community\Models\Post;
use Goldnead\Community\Services\PostService;

/** Releases posts that pre-moderation held back. */
class ApprovePost extends ModerationAction
{
    protected static $handle = 'community_approve_post';

    protected $confirm = false;

    public static function title()
    {
        return __('Approve');
    }

    public function icon(): string
    {
        return 'checkmark';
    }

    public function visibleTo($item)
    {
        return $item instanceof Post && $item->status === Post::PENDING;
    }

    public function buttonText()
    {
        return __('Approve');
    }

    public function run($items, $values)
    {
        $service = app(PostService::class);
        $items->each(fn (Post $post) => $service->approve($post, $this->moderator()));

        return trans_choice('{1} Post approved|[2,*] :count posts approved', $items->count());
    }
}
