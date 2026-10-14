<?php

namespace Goldnead\Community\Actions;

use Goldnead\Community\Models\Comment;
use Goldnead\Community\Models\Post;
use Goldnead\Community\Services\PostService;

/** Takes posts or comments off the public pages. Nothing is deleted. */
class HideContent extends ModerationAction
{
    protected static $handle = 'community_hide';

    protected $dangerous = true;

    public static function title()
    {
        return __('Hide');
    }

    public function icon(): string
    {
        return 'eye-closed';
    }

    public function visibleTo($item)
    {
        return $this->isContent($item) && ! $item->getAttribute('hidden');
    }

    protected function fieldItems()
    {
        return [
            'reason' => [
                'type' => 'text',
                'display' => __('Reason'),
                'instructions' => __('Only moderators see this.'),
                'validate' => ['nullable', 'string', 'max:255'],
            ],
        ];
    }

    public function buttonText()
    {
        return __('Hide');
    }

    public function confirmationText()
    {
        return trans_choice('{1} The item disappears from the public pages. You can show it again at any time.|[2,*] These :count items disappear from the public pages. You can show them again at any time.', $this->items->count());
    }

    public function run($items, $values)
    {
        $service = app(PostService::class);
        $reason = isset($values['reason']) ? (string) $values['reason'] : null;
        $items->each(fn (Post|Comment $item) => $service->hide($item, $this->moderator(), true, $reason));

        return trans_choice('{1} Hidden|[2,*] :count items hidden', $items->count());
    }
}
