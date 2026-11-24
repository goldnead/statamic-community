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
        // Counted by outcome: a post moderation hid, or one another moderator
        // approved a moment ago, is not "approved" by this click.
        $approved = $items->filter(fn (Post $post) => $service->claimApproval($post, $this->moderator()))->count();

        return trans_choice('{1} Post approved|[2,*] :count posts approved', $approved);
    }
}
